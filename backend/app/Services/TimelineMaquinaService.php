<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Maquina;
use App\Models\SessaoTrabalho;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimelineMaquinaService
{
    public function __construct(
        private readonly TurnoCalculoService $calculo,
        private readonly MovimentacaoDiaService $movimentacao,
    ) {}

    /**
     * Linha do tempo de cada máquina ativa para um dia: segmentos ordenados
     * de setup/produção/pausa dentro da janela do turno, com os trechos sem
     * apontamento marcados como "parado" — limitado ao instante atual, para
     * que a timeline vá se preenchendo ao longo do dia em vez de marcar como
     * parado um período que ainda não aconteceu.
     *
     * Retorna turno: null se não houver turno ativo configurado para o dia
     * da semana informado E nenhuma movimentação real tiver ocorrido nesse
     * dia. Se houver movimentação (ex.: sábado avulso trabalhado), usa-se a
     * janela de fallback de TurnoCalculoService::turnoFallback().
     *
     * @return array{turno: array<string, mixed>|null, maquinas: array<int, array<string, mixed>>}
     */
    public function timelineDoDia(Carbon $data, ?int $maquinaId = null, ?int $grupoId = null): array
    {
        $maquinas = Maquina::query()
            ->where('ativa', true)
            ->with('etapaFluxo')
            ->when($maquinaId, fn ($query) => $query->where('id', $maquinaId))
            ->when($grupoId, fn ($query) => $query->where('etapa_fluxo_id', $grupoId))
            ->get();

        $turnoDoDia = Turno::doDia($data->dayOfWeekIso, $data);
        $turno = $turnoDoDia;

        if (! $turno) {
            $temMovimentacao = ! $maquinas->isEmpty() && $this->movimentacao->existeParaMaquinas(
                $data->copy()->startOfDay(),
                $data->copy()->endOfDay(),
                $maquinas->pluck('id'),
            );

            if (! $temMovimentacao) {
                return ['turno' => null, 'maquinas' => []];
            }

            // Só para exibição no campo "turno" da resposta — o cálculo dos
            // segmentos usa a janela de cada sessão (ver janelaSessaoOuFallback()).
            $turno = $this->calculo->turnoFallback();
        }

        $janelasCompartilhadas = $turnoDoDia ? $this->calculo->janelasUteis($turnoDoDia, $data) : null;
        $agora = Carbon::now();

        if ($maquinas->isEmpty()) {
            return ['turno' => $this->turnoParaResposta($turno), 'maquinas' => []];
        }

        // Limites amplos (dia calendário inteiro): cada sessão pode ter sua
        // própria janela quando não há turno cadastrado (ver janelaSessaoOuFallback()).
        // withTrashed(): sessões canceladas (soft-deleted) só entram se ainda
        // restar algum apontamento (necessariamente finalizado) — mesmo
        // critério usado em RelatorioProducaoService::relatorioMaquinasPorPeriodo.
        $sessoesPorMaquina = SessaoTrabalho::withTrashed()
            ->with('apontamentos.pausas.motivoPausa', 'pausasOciosas.motivoPausa')
            ->whereIn('maquina_id', $maquinas->pluck('id'))
            ->where('inicio', '<=', $data->copy()->endOfDay())
            ->where(function ($query) use ($data) {
                $query->whereNull('fim')->orWhere('fim', '>=', $data->copy()->startOfDay());
            })
            ->where(function ($query) {
                $query->whereNull('deleted_at')->orWhereHas('apontamentos');
            })
            ->get()
            ->groupBy('maquina_id');

        $resultado = [];

        foreach ($maquinas as $maquina) {
            $sessoesDaMaquina = $sessoesPorMaquina->get($maquina->id, collect());

            // Turno cadastrado: janela única compartilhada por todas as sessões
            // do dia — a extensão por hora extra é calculada uma única vez, com
            // as sessões somadas (evita duplicar a mesma janela quando a máquina
            // teve 2+ sessões no dia).
            if ($turnoDoDia) {
                [$segmentos, $janelasParaLacunas] = $this->segmentosComJanelaCompartilhada(
                    $sessoesDaMaquina,
                    $janelasCompartilhadas,
                    $data,
                    $agora,
                );
            } else {
                // Sem turno cadastrado: cada sessão define sua própria janela
                // (turno informado pelo operário ou fallback) — processada de
                // forma independente, já que 2 sessões no mesmo dia podem ter
                // janelas distintas.
                [$segmentos, $janelasParaLacunas] = $this->segmentosPorSessao($sessoesDaMaquina, $data, $agora);
            }

            usort($segmentos, fn (array $a, array $b) => $a['inicio']->timestamp <=> $b['inicio']->timestamp);

            $segmentos = $this->preencherLacunasComParado($segmentos, $janelasParaLacunas, $agora);

            $resultado[] = [
                'maquina_id' => $maquina->id,
                'maquina' => $maquina->nome,
                'grupo' => $maquina->etapaFluxo
                    ? ['id' => $maquina->etapaFluxo->id, 'nome' => $maquina->etapaFluxo->nome]
                    : null,
                'segmentos' => array_map(fn (array $s) => [
                    'tipo' => $s['tipo'],
                    'inicio' => $s['inicio']->toISOString(),
                    'fim' => $s['fim']->toISOString(),
                    'motivo' => $s['motivo'] ?? null,
                ], $segmentos),
            ];
        }

        return [
            'turno' => $this->turnoParaResposta($turno),
            'maquinas' => $resultado,
        ];
    }

    /**
     * Segmentos de uma máquina cujo dia tem turno cadastrado: todas as
     * sessões compartilham a mesma janela, cuja extensão por hora extra é
     * calculada uma única vez a partir da soma de todas elas — nunca
     * duplicada por sessão.
     *
     * @param  Collection<int, SessaoTrabalho>  $sessoes
     * @param  array<int, array{inicio: Carbon, fim: Carbon}>  $janelas
     * @return array{0: array<int, array{tipo: string, inicio: Carbon, fim: Carbon, motivo?: string|null}>, 1: array<int, array{inicio: Carbon, fim: Carbon}>}
     */
    private function segmentosComJanelaCompartilhada(Collection $sessoes, array $janelas, Carbon $data, Carbon $agora): array
    {
        $diaFim      = $janelas[array_key_last($janelas)]['fim'];
        $janelaExtra = $this->calculo->janelaHoraExtra($diaFim, $data);

        $segmentos      = [];
        $segmentosExtra = [];

        foreach ($sessoes as $sessao) {
            [$segs, $extras] = $this->segmentosDaSessao($sessao, $janelas, $janelaExtra, $agora);
            array_push($segmentos, ...$segs);
            array_push($segmentosExtra, ...$extras);
        }

        // Só estende a janela usada para preencher "parado" até onde houve
        // hora extra real — sem atividade após o hora_fim, a janela desta
        // máquina permanece igual à de hoje, sem fabricar parado até 19h.
        $janelasParaLacunas = $janelas;

        if ($segmentosExtra !== []) {
            $fimHoraExtra = collect($segmentosExtra)->max(fn (array $s) => $s['fim']->timestamp);
            $janelasParaLacunas[array_key_last($janelasParaLacunas)]['fim'] = Carbon::createFromTimestamp($fimHoraExtra, $diaFim->getTimezone());
            array_push($segmentos, ...$segmentosExtra);
        }

        return [$segmentos, $janelasParaLacunas];
    }

    /**
     * Segmentos de uma máquina em dia sem turno cadastrado: cada sessão
     * define sua própria janela (turno informado pelo operário ou fallback)
     * e sua própria extensão por hora extra, processadas independentemente —
     * deduplicadas quando coincidem (ex.: sessões antigas sem turno
     * informado, todas caindo no mesmo fallback).
     *
     * @param  Collection<int, SessaoTrabalho>  $sessoes
     * @return array{0: array<int, array{tipo: string, inicio: Carbon, fim: Carbon, motivo?: string|null}>, 1: array<int, array{inicio: Carbon, fim: Carbon}>}
     */
    private function segmentosPorSessao(Collection $sessoes, Carbon $data, Carbon $agora): array
    {
        $segmentos          = [];
        $janelasVistas      = [];
        $janelasParaLacunas = [];

        foreach ($sessoes as $sessao) {
            $janelas     = $this->janelaSessaoOuFallback($sessao, $data);
            $diaFim      = $janelas[array_key_last($janelas)]['fim'];
            $janelaExtra = $this->calculo->janelaHoraExtra($diaFim, $data);

            [$segs, $segmentosExtra] = $this->segmentosDaSessao($sessao, $janelas, $janelaExtra, $agora);
            array_push($segmentos, ...$segs);

            $janelaSessaoComExtra = $janelas;

            if ($segmentosExtra !== []) {
                $fimHoraExtra = collect($segmentosExtra)->max(fn (array $s) => $s['fim']->timestamp);
                $janelaSessaoComExtra[array_key_last($janelaSessaoComExtra)]['fim'] = Carbon::createFromTimestamp($fimHoraExtra, $diaFim->getTimezone());
                array_push($segmentos, ...$segmentosExtra);
            }

            foreach ($janelaSessaoComExtra as $janela) {
                $chave = $janela['inicio']->timestamp.'|'.$janela['fim']->timestamp;

                if (! isset($janelasVistas[$chave])) {
                    $janelasVistas[$chave] = true;
                    $janelasParaLacunas[]  = $janela;
                }
            }
        }

        return [$segmentos, $janelasParaLacunas];
    }

    /**
     * Segmentos de setup/produção/pausa de uma sessão dentro da janela
     * normal e, separadamente, dentro da janela de hora extra (se houver).
     *
     * @param  array<int, array{inicio: Carbon, fim: Carbon}>  $janelas
     * @param  array{inicio: Carbon, fim: Carbon}|null  $janelaExtra
     * @return array{0: array<int, array{tipo: string, inicio: Carbon, fim: Carbon, motivo?: string|null}>, 1: array<int, array{tipo: string, inicio: Carbon, fim: Carbon, motivo?: string|null}>}
     */
    private function segmentosDaSessao(SessaoTrabalho $sessao, array $janelas, ?array $janelaExtra, Carbon $agora): array
    {
        $segmentos      = [];
        $segmentosExtra = [];

        foreach ($sessao->apontamentos as $apontamento) {
            foreach (['setup', 'producao'] as $fase) {
                array_push($segmentos, ...$this->calculo->segmentarFaseNoDia($apontamento, $fase, $janelas, $agora));

                if ($janelaExtra) {
                    array_push($segmentosExtra, ...$this->calculo->segmentarFaseNoDia($apontamento, $fase, [$janelaExtra], $agora));
                }
            }

            // "aguardando" não tem janela de trabalho ativa própria (ver
            // segmentarFaseNoDia) — só as pausas explícitas nessa fase
            // aparecem na timeline; o restante do tempo permanece "parado".
            $pausasAguardando = $apontamento->pausas->where('fase', 'aguardando');
            array_push($segmentos, ...$this->calculo->segmentarPausasAvulsas($pausasAguardando, $janelas, $agora));

            if ($janelaExtra) {
                array_push($segmentosExtra, ...$this->calculo->segmentarPausasAvulsas($pausasAguardando, [$janelaExtra], $agora));
            }
        }

        // Pausas ociosas: sessão pausada sem nenhum apontamento em andamento.
        array_push($segmentos, ...$this->calculo->segmentarPausasAvulsas($sessao->pausasOciosas, $janelas, $agora));

        if ($janelaExtra) {
            array_push($segmentosExtra, ...$this->calculo->segmentarPausasAvulsas($sessao->pausasOciosas, [$janelaExtra], $agora));
        }

        return [$segmentos, $segmentosExtra];
    }

    /**
     * Janela útil da sessão quando não há turno cadastrado para o dia: turno
     * informado pelo próprio operário ao iniciá-la, ou o fallback fixo
     * 06:00-12:00 para sessões antigas sem essa informação.
     *
     * @return array<int, array{inicio: Carbon, fim: Carbon}>
     */
    private function janelaSessaoOuFallback(SessaoTrabalho $sessao, Carbon $data): array
    {
        if ($sessao->turno_informado_inicio && $sessao->turno_informado_fim) {
            return $this->calculo->janelasInformadas($sessao->turno_informado_inicio, $sessao->turno_informado_fim, $data);
        }

        return $this->calculo->janelasUteis($this->calculo->turnoFallback(), $data);
    }

    /**
     * Preenche, dentro das janelas úteis do turno, os trechos sem nenhum
     * segmento com "parado" — recortado em `agora` para não marcar como
     * parado um período que ainda não aconteceu.
     *
     * @param  array<int, array{tipo: string, inicio: Carbon, fim: Carbon}>  $segmentos
     * @param  array<int, array{inicio: Carbon, fim: Carbon}>  $janelas
     * @return array<int, array{tipo: string, inicio: Carbon, fim: Carbon}>
     */
    private function preencherLacunasComParado(array $segmentos, array $janelas, Carbon $agora): array
    {
        $comLacunas = $segmentos;

        foreach ($janelas as $janela) {
            $limite = $this->calculo->menor($janela['fim'], $agora);

            if ($limite->lessThanOrEqualTo($janela['inicio'])) {
                continue;
            }

            $cursor = $janela['inicio']->copy();

            $segmentosNaJanela = collect($segmentos)
                ->filter(fn (array $s) => $s['inicio']->lessThan($janela['fim']) && $s['fim']->greaterThan($janela['inicio']))
                ->sortBy(fn (array $s) => $s['inicio']->timestamp)
                ->values();

            foreach ($segmentosNaJanela as $segmento) {
                $inicioClip = $this->calculo->maior($segmento['inicio'], $janela['inicio']);

                if ($inicioClip->greaterThan($cursor)) {
                    $fimLacuna = $this->calculo->menor($inicioClip, $limite);

                    if ($fimLacuna->greaterThan($cursor)) {
                        $comLacunas[] = ['tipo' => 'parado', 'inicio' => $cursor->copy(), 'fim' => $fimLacuna->copy()];
                    }
                }

                $fimClip = $this->calculo->menor($segmento['fim'], $janela['fim']);

                if ($fimClip->greaterThan($cursor)) {
                    $cursor = $fimClip->copy();
                }
            }

            if ($limite->greaterThan($cursor)) {
                $comLacunas[] = ['tipo' => 'parado', 'inicio' => $cursor->copy(), 'fim' => $limite->copy()];
            }
        }

        usort($comLacunas, fn (array $a, array $b) => $a['inicio']->timestamp <=> $b['inicio']->timestamp);

        return $comLacunas;
    }

    /** @return array<string, mixed> */
    private function turnoParaResposta(Turno $turno): array
    {
        return [
            'hora_inicio' => $turno->hora_inicio,
            'hora_fim' => $turno->hora_fim,
            'intervalo_inicio' => $turno->intervalo_inicio,
            'intervalo_fim' => $turno->intervalo_fim,
        ];
    }
}
