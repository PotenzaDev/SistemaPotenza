<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Apontamento;
use App\Models\Maquina;
use App\Models\SessaoTrabalho;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RelatorioProducaoService
{
    public function __construct(
        private readonly TurnoCalculoService $calculo,
        private readonly MovimentacaoDiaService $movimentacao,
    ) {}

    /**
     * Relatório de tempo de turno (trabalhado, pausas e ocioso) por sessão de
     * trabalho (operário + máquina), para o dia informado.
     *
     * Um apontamento pode atravessar a virada do turno (ex.: pausado às 17h
     * pelo "Fim de Turno" e retomado no dia seguinte às 8h): o intervalo de
     * cada fase (setup/produção) é recortado para dentro da janela
     * [hora_inicio, hora_fim] do turno do dia, então cada dia recebe apenas a
     * sua fatia. Se o turno tiver um intervalo de almoço configurado, essa
     * janela é removida do cálculo (não conta como tempo de turno nem como
     * tempo trabalhado).
     *
     * Retorna [] se não houver turno ativo configurado para o dia da semana
     * E nenhuma movimentação (setup/produção) real tiver ocorrido nesse dia.
     * Quando não há turno cadastrado (ex.: fim de semana), cada sessão usa sua
     * própria janela: o turno informado pelo próprio operário ao iniciá-la
     * (ver TurnoCalculoService::janelasInformadas()) ou, para sessões antigas
     * sem essa informação, a janela de fallback fixa de turnoFallback() — ver
     * janelasParaSessao().
     *
     * @return array<int, array<string, mixed>>
     */
    public function relatorioPorDia(Carbon $data, ?int $operarioId = null, ?int $maquinaId = null): array
    {
        $turnoDoDia = Turno::doDia($data->dayOfWeekIso, $data);

        if (! $turnoDoDia) {
            $temMovimentacao = $this->movimentacao->existeParaSessao(
                $data->copy()->startOfDay(),
                $data->copy()->endOfDay(),
                $operarioId,
                $maquinaId,
            );

            if (! $temMovimentacao) {
                return [];
            }
        }

        $agora = Carbon::now();

        // Limites amplos (dia calendário inteiro, não a janela do turno): cada
        // sessão pode ter sua própria janela (turno do dia, turno informado
        // pelo operário no fim de semana, ou fallback) — ver janelasParaSessao().
        // withTrashed(): sessões canceladas (soft-deleted) só entram se ainda
        // restar algum apontamento (necessariamente finalizado, já que os não
        // finalizados são excluídos no cancelamento) — evita linhas "fantasma"
        // de sessões totalmente canceladas sem nenhuma produção real.
        $sessoes = SessaoTrabalho::withTrashed()
            ->with(['operario.user', 'maquina', 'apontamentos.pausas.motivoPausa', 'pausasOciosas.motivoPausa'])
            ->where('inicio', '<=', $data->copy()->endOfDay())
            ->where(function ($query) use ($data) {
                $query->whereNull('fim')->orWhere('fim', '>=', $data->copy()->startOfDay());
            })
            ->where(function ($query) {
                $query->whereNull('deleted_at')->orWhereHas('apontamentos');
            })
            ->when($operarioId, fn ($query) => $query->where('operario_id', $operarioId))
            ->when($maquinaId, fn ($query) => $query->where('maquina_id', $maquinaId))
            ->get();

        return $sessoes->map(function (SessaoTrabalho $sessao) use ($turnoDoDia, $data, $agora) {
            $janelas            = $this->janelasParaSessao($turnoDoDia, $sessao, $data);
            $diaFim             = $janelas[array_key_last($janelas)]['fim'];
            $tempoTurnoSegundos = $this->calculo->somaDuracaoJanelas($janelas);
            $janelaExtra        = $this->calculo->janelaHoraExtra($diaFim, $data);

            $trabalhadoSegundos      = 0;
            $trabalhadoExtraSegundos = 0;
            $pausaExtraSegundos      = 0;
            $pausasPorMotivo         = [];

            $somarPausas = function (array $pausas) use (&$pausasPorMotivo) {
                foreach ($pausas as $motivo => $segundos) {
                    $pausasPorMotivo[$motivo] = ($pausasPorMotivo[$motivo] ?? 0) + $segundos;
                }
            };

            foreach ($sessao->apontamentos as $apontamento) {
                foreach (['setup', 'producao'] as $fase) {
                    [$trabalhado, $pausas] = $this->calculo->calcularFaseNoDia($apontamento, $fase, $janelas, $agora);

                    $trabalhadoSegundos += $trabalhado;
                    $somarPausas($pausas);

                    // Hora extra: apontamento além do hora_fim do turno, até 19h —
                    // só entra se realmente houve atividade nessa janela (nunca
                    // fabrica ocioso/turno para o período sem apontamento).
                    if ($janelaExtra) {
                        [$trabalhadoExtra, $pausasExtra] = $this->calculo->calcularFaseNoDia($apontamento, $fase, [$janelaExtra], $agora);

                        $trabalhadoExtraSegundos += $trabalhadoExtra;
                        $pausaExtraSegundos      += array_sum($pausasExtra);
                        $somarPausas($pausasExtra);
                    }
                }

                // "aguardando" não conta como trabalhado — só as pausas explícitas
                // nessa fase entram no total de pausa (o restante vira ocioso).
                $pausasAguardando = $apontamento->pausas->where('fase', 'aguardando');

                $somarPausas($this->calculo->calcularPausasAvulsas($pausasAguardando, $janelas, $agora));

                if ($janelaExtra) {
                    $pausasAguardandoExtra = $this->calculo->calcularPausasAvulsas($pausasAguardando, [$janelaExtra], $agora);
                    $pausaExtraSegundos   += array_sum($pausasAguardandoExtra);
                    $somarPausas($pausasAguardandoExtra);
                }
            }

            // Pausas ociosas: sessão pausada sem nenhum apontamento em andamento.
            $somarPausas($this->calculo->calcularPausasAvulsas($sessao->pausasOciosas, $janelas, $agora));

            if ($janelaExtra) {
                $pausasOciosasExtra = $this->calculo->calcularPausasAvulsas($sessao->pausasOciosas, [$janelaExtra], $agora);
                $pausaExtraSegundos += array_sum($pausasOciosasExtra);
                $somarPausas($pausasOciosasExtra);
            }

            // tempo_turno_segundos só cresce pelo tanto de hora extra realmente
            // trabalhada/pausada — se não houve atividade após o hora_fim, os
            // três valores abaixo ficam idênticos ao cálculo sem hora extra.
            $pausaSegundos      = array_sum($pausasPorMotivo);
            $tempoTurnoComExtra = $tempoTurnoSegundos + $trabalhadoExtraSegundos + $pausaExtraSegundos;
            $trabalhadoComExtra = $trabalhadoSegundos + $trabalhadoExtraSegundos;
            $ociosoSegundos     = max(0, $tempoTurnoComExtra - $trabalhadoComExtra - $pausaSegundos);

            return [
                'sessao_id'                 => $sessao->id,
                'operario_id'               => $sessao->operario_id,
                'operario'                  => $sessao->operario?->user?->name,
                'maquina_id'                => $sessao->maquina_id,
                'maquina'                   => $sessao->maquina?->nome,
                'tempo_turno_segundos'      => $tempoTurnoComExtra,
                'tempo_trabalhado_segundos' => $trabalhadoComExtra,
                'tempo_extra_segundos'      => $trabalhadoExtraSegundos,
                'tempo_pausa_segundos'      => $pausaSegundos,
                'tempo_ocioso_segundos'     => $ociosoSegundos,
                'pausas_por_motivo'         => $pausasPorMotivo,
                'percentual_produtivo'      => $tempoTurnoComExtra > 0
                    ? round($trabalhadoComExtra / $tempoTurnoComExtra * 100, 1)
                    : 0.0,
            ];
        })->values()->all();
    }

    /**
     * Relatório de produção por máquina, agregando turno, setup, produção,
     * tempo parado e quantidade de peças produzidas em um intervalo de dias.
     *
     * Um dia só é contabilizado para uma máquina se existiu movimentação real
     * (setup ou produção) daquela máquina naquele dia — não basta o dia da
     * semana ter turno ativo configurado. Isso evita que feriados caídos em
     * dia de semana normalmente ativo inflem o tempo de turno sem nenhuma
     * produção real, e permite que sábados/domingos com serviço avulso
     * apareçam no relatório mesmo sem turno cadastrado — nesse caso, cada
     * sessão usa a janela informada pelo próprio operário ao iniciá-la, ou a
     * janela de fallback fixa 06:00-12:00 para sessões antigas sem essa
     * informação (ver janelasParaSessao()). Cada máquina conta seus próprios
     * dias com movimentação de forma independente das demais.
     *
     * @return array{maquinas: array<int, array<string, mixed>>, totais: array<string, mixed>, dias_considerados: int}
     */
    public function relatorioMaquinasPorPeriodo(Carbon $dataInicio, Carbon $dataFim, ?int $maquinaId = null, ?int $grupoId = null): array
    {
        $maquinas = Maquina::query()
            ->where('ativa', true)
            ->with('etapaFluxo')
            ->when($maquinaId, fn ($query) => $query->where('id', $maquinaId))
            ->when($grupoId, fn ($query) => $query->where('etapa_fluxo_id', $grupoId))
            ->get();

        if ($maquinas->isEmpty()) {
            return ['maquinas' => [], 'totais' => $this->totaisVaziosRelatorioMaquinas(), 'dias_considerados' => 0];
        }

        $periodoInicio = $dataInicio->copy()->startOfDay();
        $periodoFim    = $dataFim->copy()->endOfDay();
        $agora         = Carbon::now();

        // withTrashed(): sessões canceladas (soft-deleted) só entram se ainda
        // restar algum apontamento (necessariamente finalizado) — evita
        // sessões totalmente canceladas sem nenhuma produção real.
        $sessoesPorMaquina = SessaoTrabalho::withTrashed()
            ->with(['apontamentos.pausas.motivoPausa', 'apontamentos.fichas'])
            ->whereIn('maquina_id', $maquinas->pluck('id'))
            ->where('inicio', '<=', $periodoFim)
            ->where(function ($query) use ($periodoInicio) {
                $query->whereNull('fim')->orWhere('fim', '>=', $periodoInicio);
            })
            ->where(function ($query) {
                $query->whereNull('deleted_at')->orWhereHas('apontamentos');
            })
            ->get()
            ->groupBy('maquina_id');

        $acumulado = [];

        foreach ($maquinas as $maquina) {
            $acumulado[$maquina->id] = [
                'maquina_id'              => $maquina->id,
                'maquina'                 => $maquina->nome,
                'grupo'                   => $maquina->etapaFluxo
                    ? ['id' => $maquina->etapaFluxo->id, 'nome' => $maquina->etapaFluxo->nome]
                    : null,
                'tempo_turno_segundos'    => 0,
                'tempo_setup_segundos'    => 0,
                'tempo_producao_segundos' => 0,
                'tempo_extra_segundos'    => 0,
                'qtd_pecas'               => 0,
                'dias_com_movimentacao'   => 0,
            ];
        }

        $diasComMovimentoNoPeriodo = [];
        $cursor                    = $periodoInicio->copy();

        while ($cursor->lessThanOrEqualTo($periodoFim)) {
            $inicioDia = $cursor->copy()->startOfDay();
            $fimDia    = $cursor->copy()->endOfDay();

            $turnoDoDia = Turno::doDia($cursor->dayOfWeekIso, $cursor);

            // Turno cadastrado: janela única, compartilhada por todas as sessões
            // do dia — tempo_turno_segundos é somado uma única vez por máquina
            // (nunca por sessão, senão duplicaria em dias com 2+ sessões).
            $janelasCompartilhadas = null;
            $janelaExtraCompartilhada = null;
            $tempoTurnoCompartilhado = 0;

            if ($turnoDoDia) {
                $janelasCompartilhadas    = $this->calculo->janelasUteis($turnoDoDia, $cursor);
                $diaFimCompartilhado      = $janelasCompartilhadas[array_key_last($janelasCompartilhadas)]['fim'];
                $tempoTurnoCompartilhado  = $this->calculo->somaDuracaoJanelas($janelasCompartilhadas);
                $janelaExtraCompartilhada = $this->calculo->janelaHoraExtra($diaFimCompartilhado, $cursor);
            }

            foreach ($maquinas as $maquina) {
                /** @var Collection<int, SessaoTrabalho> $sessoes */
                $sessoes = $sessoesPorMaquina->get($maquina->id, collect());

                if (! $this->existeMovimentacaoNoDia($sessoes, $inicioDia, $fimDia, $agora)) {
                    continue;
                }

                $diasComMovimentoNoPeriodo[$cursor->toDateString()] = true;
                $acumulado[$maquina->id]['dias_com_movimentacao']++;

                if ($turnoDoDia) {
                    $trabalhadoExtraMaquina = 0;

                    foreach ($sessoes as $sessao) {
                        $trabalhadoExtraMaquina += $this->acumularSessaoNoDia(
                            $acumulado,
                            $maquina->id,
                            $sessao,
                            $janelasCompartilhadas,
                            $janelaExtraCompartilhada,
                            $inicioDia,
                            $fimDia,
                            $agora,
                        );
                    }

                    $acumulado[$maquina->id]['tempo_turno_segundos'] += $tempoTurnoCompartilhado + $trabalhadoExtraMaquina;
                    $acumulado[$maquina->id]['tempo_extra_segundos'] += $trabalhadoExtraMaquina;

                    continue;
                }

                // Sem turno cadastrado (fim de semana): cada sessão define sua
                // própria janela (turno informado pelo operário ou fallback) —
                // somada de forma independente por sessão, já que 2 sessões no
                // mesmo dia/máquina podem ter janelas distintas.
                foreach ($sessoes as $sessao) {
                    $janelasSessao   = $this->janelasParaSessao(null, $sessao, $cursor);
                    $diaFimSessao    = $janelasSessao[array_key_last($janelasSessao)]['fim'];
                    $tempoTurnoSessao = $this->calculo->somaDuracaoJanelas($janelasSessao);
                    $janelaExtraSessao = $this->calculo->janelaHoraExtra($diaFimSessao, $cursor);

                    $trabalhadoExtraSessao = $this->acumularSessaoNoDia(
                        $acumulado,
                        $maquina->id,
                        $sessao,
                        $janelasSessao,
                        $janelaExtraSessao,
                        $inicioDia,
                        $fimDia,
                        $agora,
                    );

                    $acumulado[$maquina->id]['tempo_turno_segundos'] += $tempoTurnoSessao + $trabalhadoExtraSessao;
                    $acumulado[$maquina->id]['tempo_extra_segundos'] += $trabalhadoExtraSessao;
                }
            }

            $cursor->addDay();
        }

        $totais            = $this->totaisVaziosRelatorioMaquinas();
        $maquinasResultado = [];

        foreach ($acumulado as $dados) {
            $tempoTurno    = $dados['tempo_turno_segundos'];
            $tempoSetup    = $dados['tempo_setup_segundos'];
            $tempoProducao = $dados['tempo_producao_segundos'];
            $tempoParado   = max(0, $tempoTurno - $tempoSetup - $tempoProducao);

            $maquinasResultado[] = [
                'maquina_id'              => $dados['maquina_id'],
                'maquina'                 => $dados['maquina'],
                'grupo'                   => $dados['grupo'],
                'tempo_turno_segundos'    => $tempoTurno,
                'tempo_setup_segundos'    => $tempoSetup,
                'tempo_producao_segundos' => $tempoProducao,
                'tempo_parado_segundos'   => $tempoParado,
                'tempo_extra_segundos'    => $dados['tempo_extra_segundos'],
                'qtd_pecas'               => $dados['qtd_pecas'],
                'dias_com_movimentacao'   => $dados['dias_com_movimentacao'],
                'percentual_utilizacao'   => $tempoTurno > 0
                    ? round($tempoProducao / $tempoTurno * 100, 1)
                    : 0.0,
            ];

            $totais['tempo_turno_segundos']    += $tempoTurno;
            $totais['tempo_setup_segundos']    += $tempoSetup;
            $totais['tempo_producao_segundos'] += $tempoProducao;
            $totais['tempo_parado_segundos']   += $tempoParado;
            $totais['tempo_extra_segundos']    += $dados['tempo_extra_segundos'];
            $totais['qtd_pecas']               += $dados['qtd_pecas'];
        }

        return [
            'maquinas'          => $maquinasResultado,
            'totais'            => $totais,
            'dias_considerados' => count($diasComMovimentoNoPeriodo),
        ];
    }

    /**
     * Existe alguma movimentação (setup, produção ou ficha bipada) das
     * sessões informadas sobrepondo [inicioDia, fimDia]? Verificação em
     * memória (as sessões/apontamentos/fichas já vêm eager-loaded do
     * chamador) — evita uma query por dia×máquina no período.
     *
     * @param  Collection<int, SessaoTrabalho>  $sessoes
     */
    private function existeMovimentacaoNoDia(Collection $sessoes, Carbon $inicioDia, Carbon $fimDia, Carbon $agora): bool
    {
        foreach ($sessoes as $sessao) {
            foreach ($sessao->apontamentos as $apontamento) {
                if ($this->faseSobrepoeDia($apontamento->setup_inicio, $apontamento->setup_fim, $inicioDia, $fimDia, $agora)) {
                    return true;
                }

                if ($this->faseSobrepoeDia($apontamento->producao_inicio, $apontamento->producao_fim, $inicioDia, $fimDia, $agora)) {
                    return true;
                }

                foreach ($apontamento->fichas as $ficha) {
                    if ($ficha->bipada_at && $ficha->bipada_at->between($inicioDia, $fimDia)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function faseSobrepoeDia(?Carbon $inicio, ?Carbon $fim, Carbon $inicioDia, Carbon $fimDia, Carbon $agora): bool
    {
        if (! $inicio) {
            return false;
        }

        $fimEfetivo = $fim ?? $agora;

        return $inicio->lessThanOrEqualTo($fimDia) && $fimEfetivo->greaterThanOrEqualTo($inicioDia);
    }

    /** @return array<string, int> */
    private function totaisVaziosRelatorioMaquinas(): array
    {
        return [
            'tempo_turno_segundos'    => 0,
            'tempo_setup_segundos'    => 0,
            'tempo_producao_segundos' => 0,
            'tempo_parado_segundos'   => 0,
            'tempo_extra_segundos'    => 0,
            'qtd_pecas'               => 0,
        ];
    }

    /**
     * Janela útil a considerar para uma sessão no dia informado:
     * - turno cadastrado para o dia da semana → mesma janela para todas as
     *   sessões desse dia;
     * - sem turno cadastrado, sessão com turno informado pelo próprio
     *   operário ao iniciá-la (fim de semana avulso) → janela definida por
     *   ele para aquela máquina/sessão;
     * - sem turno cadastrado e sem turno informado (sessões antigas,
     *   anteriores a este recurso) → janela de fallback fixa 06:00-12:00.
     *
     * @return array<int, array{inicio: Carbon, fim: Carbon}>
     */
    private function janelasParaSessao(?Turno $turnoDoDia, SessaoTrabalho $sessao, Carbon $data): array
    {
        if ($turnoDoDia) {
            return $this->calculo->janelasUteis($turnoDoDia, $data);
        }

        if ($sessao->turno_informado_inicio && $sessao->turno_informado_fim) {
            return $this->calculo->janelasInformadas($sessao->turno_informado_inicio, $sessao->turno_informado_fim, $data);
        }

        return $this->calculo->janelasUteis($this->calculo->turnoFallback(), $data);
    }

    /**
     * Acumula, em $acumulado[$maquinaId], o tempo de setup/produção (dentro
     * da janela e da janela de hora extra) e a quantidade de peças de uma
     * sessão específica — usado tanto no caminho de turno compartilhado
     * quanto no de janela por sessão em relatorioMaquinasPorPeriodo().
     *
     * @param  array<int, array<string, mixed>>  $acumulado
     * @param  array<int, array{inicio: Carbon, fim: Carbon}>  $janelas
     * @param  array{inicio: Carbon, fim: Carbon}|null  $janelaExtra
     * @return int Segundos trabalhados dentro da janela de hora extra.
     */
    private function acumularSessaoNoDia(array &$acumulado, int $maquinaId, SessaoTrabalho $sessao, array $janelas, ?array $janelaExtra, Carbon $inicioDia, Carbon $fimDia, Carbon $agora): int
    {
        $trabalhadoExtra = 0;

        foreach ($sessao->apontamentos as $apontamento) {
            foreach (['setup', 'producao'] as $fase) {
                [$trabalhado] = $this->calculo->calcularFaseNoDia($apontamento, $fase, $janelas, $agora);

                $chave = $fase === 'setup' ? 'tempo_setup_segundos' : 'tempo_producao_segundos';
                $acumulado[$maquinaId][$chave] += $trabalhado;

                if ($janelaExtra) {
                    [$extra] = $this->calculo->calcularFaseNoDia($apontamento, $fase, [$janelaExtra], $agora);
                    $acumulado[$maquinaId][$chave] += $extra;
                    $trabalhadoExtra                += $extra;
                }
            }

            // Mesma regra do relatório de apontamentos (ApontamentoService::
            // fichasNoPeriodo): conta pela data de fim_producao (pilha
            // finalizada) dentro do dia calendário inteiro — não pela janela
            // do turno/hora extra, senão uma peça finalizada fora do horário
            // de turno (ex.: hora extra além das 19h) contaria na tela de
            // apontamentos mas sumiria deste relatório. Fichas ainda sem
            // fim_producao não contam.
            foreach ($apontamento->fichas as $ficha) {
                if (! $ficha->fim_producao) {
                    continue;
                }

                if ($ficha->fim_producao->between($inicioDia, $fimDia)) {
                    $acumulado[$maquinaId]['qtd_pecas'] += $ficha->qtd_peca;
                }
            }
        }

        return $trabalhadoExtra;
    }

}
