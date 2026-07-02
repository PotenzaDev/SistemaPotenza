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
     * Retorna [] se não houver turno ativo configurado para o dia da semana.
     *
     * @return array<int, array<string, mixed>>
     */
    public function relatorioPorDia(Carbon $data, ?int $operarioId = null, ?int $maquinaId = null): array
    {
        $turno = Turno::doDia($data->dayOfWeekIso, $data);

        if (! $turno) {
            return [];
        }

        $janelas            = $this->calculo->janelasUteis($turno, $data);
        $diaInicio          = $janelas[0]['inicio'];
        $diaFim             = $janelas[array_key_last($janelas)]['fim'];
        $tempoTurnoSegundos = $this->calculo->somaDuracaoJanelas($janelas);
        $agora              = Carbon::now();

        // withTrashed(): sessões canceladas (soft-deleted) só entram se ainda
        // restar algum apontamento (necessariamente finalizado, já que os não
        // finalizados são excluídos no cancelamento) — evita linhas "fantasma"
        // de sessões totalmente canceladas sem nenhuma produção real.
        $sessoes = SessaoTrabalho::withTrashed()
            ->with(['operario.user', 'maquina', 'apontamentos.pausas.motivoPausa', 'pausasOciosas.motivoPausa'])
            ->where('inicio', '<=', $diaFim)
            ->where(function ($query) use ($diaInicio) {
                $query->whereNull('fim')->orWhere('fim', '>=', $diaInicio);
            })
            ->where(function ($query) {
                $query->whereNull('deleted_at')->orWhereHas('apontamentos');
            })
            ->when($operarioId, fn ($query) => $query->where('operario_id', $operarioId))
            ->when($maquinaId, fn ($query) => $query->where('maquina_id', $maquinaId))
            ->get();

        return $sessoes->map(function (SessaoTrabalho $sessao) use ($janelas, $tempoTurnoSegundos, $agora) {
            $trabalhadoSegundos = 0;
            $pausasPorMotivo    = [];

            foreach ($sessao->apontamentos as $apontamento) {
                foreach (['setup', 'producao'] as $fase) {
                    [$trabalhado, $pausas] = $this->calculo->calcularFaseNoDia($apontamento, $fase, $janelas, $agora);

                    $trabalhadoSegundos += $trabalhado;

                    foreach ($pausas as $motivo => $segundos) {
                        $pausasPorMotivo[$motivo] = ($pausasPorMotivo[$motivo] ?? 0) + $segundos;
                    }
                }

                // "aguardando" não conta como trabalhado — só as pausas explícitas
                // nessa fase entram no total de pausa (o restante vira ocioso).
                $pausasAguardando = $apontamento->pausas->where('fase', 'aguardando');

                foreach ($this->calculo->calcularPausasAvulsas($pausasAguardando, $janelas, $agora) as $motivo => $segundos) {
                    $pausasPorMotivo[$motivo] = ($pausasPorMotivo[$motivo] ?? 0) + $segundos;
                }
            }

            // Pausas ociosas: sessão pausada sem nenhum apontamento em andamento.
            foreach ($this->calculo->calcularPausasAvulsas($sessao->pausasOciosas, $janelas, $agora) as $motivo => $segundos) {
                $pausasPorMotivo[$motivo] = ($pausasPorMotivo[$motivo] ?? 0) + $segundos;
            }

            $pausaSegundos  = array_sum($pausasPorMotivo);
            $ociosoSegundos = max(0, $tempoTurnoSegundos - $trabalhadoSegundos - $pausaSegundos);

            return [
                'sessao_id'                 => $sessao->id,
                'operario_id'               => $sessao->operario_id,
                'operario'                  => $sessao->operario?->user?->name,
                'maquina_id'                => $sessao->maquina_id,
                'maquina'                   => $sessao->maquina?->nome,
                'tempo_turno_segundos'      => $tempoTurnoSegundos,
                'tempo_trabalhado_segundos' => $trabalhadoSegundos,
                'tempo_pausa_segundos'      => $pausaSegundos,
                'tempo_ocioso_segundos'     => $ociosoSegundos,
                'pausas_por_motivo'         => $pausasPorMotivo,
                'percentual_produtivo'      => $tempoTurnoSegundos > 0
                    ? round($trabalhadoSegundos / $tempoTurnoSegundos * 100, 1)
                    : 0.0,
            ];
        })->values()->all();
    }

    /**
     * Relatório de produção por máquina, agregando turno, setup, produção,
     * tempo parado e quantidade de peças produzidas em um intervalo de dias.
     *
     * Para cada dia do período, soma-se o tempo de turno, setup e produção
     * de cada máquina dentro da janela [hora_inicio, hora_fim] do turno
     * daquele dia da semana. Dias sem turno configurado são ignorados (não
     * contam para tempo de turno nem para tempo parado).
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
                'qtd_pecas'               => 0,
            ];
        }

        $diasConsiderados = 0;
        $cursor           = $periodoInicio->copy();

        while ($cursor->lessThanOrEqualTo($periodoFim)) {
            $turno = Turno::doDia($cursor->dayOfWeekIso, $cursor);

            if ($turno) {
                $diasConsiderados++;

                $janelas            = $this->calculo->janelasUteis($turno, $cursor);
                $diaInicio          = $janelas[0]['inicio'];
                $diaFimDia          = $janelas[array_key_last($janelas)]['fim'];
                $tempoTurnoSegundos = $this->calculo->somaDuracaoJanelas($janelas);

                foreach ($maquinas as $maquina) {
                    $acumulado[$maquina->id]['tempo_turno_segundos'] += $tempoTurnoSegundos;

                    /** @var Collection<int, SessaoTrabalho> $sessoes */
                    $sessoes = $sessoesPorMaquina->get($maquina->id, collect());

                    foreach ($sessoes as $sessao) {
                        foreach ($sessao->apontamentos as $apontamento) {
                            foreach (['setup', 'producao'] as $fase) {
                                [$trabalhado] = $this->calculo->calcularFaseNoDia($apontamento, $fase, $janelas, $agora);

                                $chave = $fase === 'setup' ? 'tempo_setup_segundos' : 'tempo_producao_segundos';
                                $acumulado[$maquina->id][$chave] += $trabalhado;
                            }

                            foreach ($apontamento->fichas as $ficha) {
                                if ($ficha->bipada_at && $ficha->bipada_at->between($diaInicio, $diaFimDia)) {
                                    $acumulado[$maquina->id]['qtd_pecas'] += $ficha->qtd_peca;
                                }
                            }
                        }
                    }
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
                'qtd_pecas'               => $dados['qtd_pecas'],
                'percentual_utilizacao'   => $tempoTurno > 0
                    ? round($tempoProducao / $tempoTurno * 100, 1)
                    : 0.0,
            ];

            $totais['tempo_turno_segundos']    += $tempoTurno;
            $totais['tempo_setup_segundos']    += $tempoSetup;
            $totais['tempo_producao_segundos'] += $tempoProducao;
            $totais['tempo_parado_segundos']   += $tempoParado;
            $totais['qtd_pecas']               += $dados['qtd_pecas'];
        }

        return [
            'maquinas'          => $maquinasResultado,
            'totais'            => $totais,
            'dias_considerados' => $diasConsiderados,
        ];
    }

    /** @return array<string, int> */
    private function totaisVaziosRelatorioMaquinas(): array
    {
        return [
            'tempo_turno_segundos'    => 0,
            'tempo_setup_segundos'    => 0,
            'tempo_producao_segundos' => 0,
            'tempo_parado_segundos'   => 0,
            'qtd_pecas'               => 0,
        ];
    }

}
