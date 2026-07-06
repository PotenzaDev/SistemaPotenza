<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Maquina;
use App\Models\SessaoTrabalho;
use App\Models\Turno;
use Carbon\Carbon;

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

        $turno = Turno::doDia($data->dayOfWeekIso, $data);

        if (! $turno) {
            $temMovimentacao = ! $maquinas->isEmpty() && $this->movimentacao->existeParaMaquinas(
                $data->copy()->startOfDay(),
                $data->copy()->endOfDay(),
                $maquinas->pluck('id'),
            );

            if (! $temMovimentacao) {
                return ['turno' => null, 'maquinas' => []];
            }

            $turno = $this->calculo->turnoFallback();
        }

        $janelas = $this->calculo->janelasUteis($turno, $data);
        $diaInicio = $janelas[0]['inicio'];
        $diaFim = $janelas[array_key_last($janelas)]['fim'];
        $agora = Carbon::now();

        if ($maquinas->isEmpty()) {
            return ['turno' => $this->turnoParaResposta($turno), 'maquinas' => []];
        }

        // withTrashed(): sessões canceladas (soft-deleted) só entram se ainda
        // restar algum apontamento (necessariamente finalizado) — mesmo
        // critério usado em RelatorioProducaoService::relatorioMaquinasPorPeriodo.
        $sessoesPorMaquina = SessaoTrabalho::withTrashed()
            ->with('apontamentos.pausas.motivoPausa', 'pausasOciosas.motivoPausa')
            ->whereIn('maquina_id', $maquinas->pluck('id'))
            ->where('inicio', '<=', $diaFim)
            ->where(function ($query) use ($diaInicio) {
                $query->whereNull('fim')->orWhere('fim', '>=', $diaInicio);
            })
            ->where(function ($query) {
                $query->whereNull('deleted_at')->orWhereHas('apontamentos');
            })
            ->get()
            ->groupBy('maquina_id');

        $resultado = [];

        foreach ($maquinas as $maquina) {
            $segmentos = [];

            foreach ($sessoesPorMaquina->get($maquina->id, collect()) as $sessao) {
                foreach ($sessao->apontamentos as $apontamento) {
                    foreach (['setup', 'producao'] as $fase) {
                        array_push($segmentos, ...$this->calculo->segmentarFaseNoDia($apontamento, $fase, $janelas, $agora));
                    }

                    // "aguardando" não tem janela de trabalho ativa própria (ver
                    // segmentarFaseNoDia) — só as pausas explícitas nessa fase
                    // aparecem na timeline; o restante do tempo permanece "parado".
                    $pausasAguardando = $apontamento->pausas->where('fase', 'aguardando');
                    array_push($segmentos, ...$this->calculo->segmentarPausasAvulsas($pausasAguardando, $janelas, $agora));
                }

                // Pausas ociosas: sessão pausada sem nenhum apontamento em andamento.
                array_push($segmentos, ...$this->calculo->segmentarPausasAvulsas($sessao->pausasOciosas, $janelas, $agora));
            }

            usort($segmentos, fn (array $a, array $b) => $a['inicio']->timestamp <=> $b['inicio']->timestamp);

            $segmentos = $this->preencherLacunasComParado($segmentos, $janelas, $agora);

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
