<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Apontamento;
use App\Models\Pausa;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TurnoCalculoService
{
    /**
     * Turno "virtual" (não persistido) usado como janela de cálculo quando
     * existe movimentação (setup/produção) em um dia sem nenhum turno
     * configurado — ex.: sábado avulso trabalhado sem turno cadastrado.
     * Janela fixa 06:00-12:00: tempo fora dela não é contabilizado.
     */
    public function turnoFallback(): Turno
    {
        return new Turno([
            'hora_inicio' => '06:00:00',
            'hora_fim'    => '12:00:00',
        ]);
    }

    /**
     * Janelas de tempo do turno que contam como tempo útil: o dia inteiro
     * [hora_inicio, hora_fim], ou esse período recortado pelo intervalo de
     * almoço [intervalo_inicio, intervalo_fim] quando configurado.
     *
     * @return array<int, array{inicio: Carbon, fim: Carbon}>
     */
    public function janelasUteis(Turno $turno, Carbon $data): array
    {
        $inicio = $data->copy()->setTimeFromTimeString($turno->hora_inicio);
        $fim = $data->copy()->setTimeFromTimeString($turno->hora_fim);

        if (! $turno->intervalo_inicio || ! $turno->intervalo_fim) {
            return [['inicio' => $inicio, 'fim' => $fim]];
        }

        $intervaloInicio = $data->copy()->setTimeFromTimeString($turno->intervalo_inicio);
        $intervaloFim = $data->copy()->setTimeFromTimeString($turno->intervalo_fim);

        return [
            ['inicio' => $inicio, 'fim' => $intervaloInicio],
            ['inicio' => $intervaloFim, 'fim' => $fim],
        ];
    }

    /**
     * Calcula, para uma fase (setup|producao) de um apontamento, o tempo
     * trabalhado e as pausas que caem dentro das janelas úteis do turno.
     *
     * @param  array<int, array{inicio: Carbon, fim: Carbon}>  $janelas
     * @return array{0: int, 1: array<string, int>} [trabalhadoSegundos, pausasPorMotivo]
     */
    public function calcularFaseNoDia(Apontamento $apontamento, string $fase, array $janelas, Carbon $agora): array
    {
        $inicio = $fase === 'setup' ? $apontamento->setup_inicio : $apontamento->producao_inicio;

        if (! $inicio) {
            return [0, []];
        }

        $fim = ($fase === 'setup' ? $apontamento->setup_fim : $apontamento->producao_fim) ?? $agora;

        $diaInicio = $janelas[0]['inicio'];
        $diaFim = $janelas[array_key_last($janelas)]['fim'];

        if ($fim->lessThanOrEqualTo($diaInicio) || $inicio->greaterThanOrEqualTo($diaFim)) {
            return [0, []];
        }

        $pausasFase = $apontamento->pausas->where('fase', $fase);
        $ativos = $this->subtrairIntervalos($inicio, $fim, $pausasFase, $agora);

        $trabalhado = 0;

        foreach ($ativos as $intervalo) {
            $trabalhado += $this->intersecaoComJanelas($intervalo['inicio'], $intervalo['fim'], $janelas);
        }

        $pausasPorMotivo = $this->calcularPausasAvulsas($pausasFase, $janelas, $agora);

        return [$trabalhado, $pausasPorMotivo];
    }

    /**
     * Pausas de uma fase sem janela de trabalho ativa própria (ex.: "aguardando",
     * o intervalo entre fim do setup e início da produção) somadas por motivo,
     * recortadas pelas janelas úteis do turno. Não conta como tempo trabalhado —
     * só o tempo efetivamente pausado é contabilizado.
     *
     * @param  Collection<int, Pausa>  $pausas
     * @param  array<int, array{inicio: Carbon, fim: Carbon}>  $janelas
     * @return array<string, int>
     */
    public function calcularPausasAvulsas(Collection $pausas, array $janelas, Carbon $agora): array
    {
        $pausasPorMotivo = [];

        foreach ($pausas as $pausa) {
            // "Fim de Turno" é pausa automática de fechamento, não conta como pausa real.
            if ($pausa->motivoPausa?->nome === 'Fim de Turno') {
                continue;
            }

            $segundos = $this->intersecaoComJanelas($pausa->inicio, $pausa->fim ?? $agora, $janelas);

            if ($segundos > 0) {
                $motivo = $pausa->motivoPausa?->nome ?? 'Outro';
                $pausasPorMotivo[$motivo] = ($pausasPorMotivo[$motivo] ?? 0) + $segundos;
            }
        }

        return $pausasPorMotivo;
    }

    /**
     * Segmenta os intervalos ativos de uma fase (setup|producao) e as pausas
     * dessa fase, recortados pelas janelas úteis do turno — usado para montar
     * a linha do tempo da máquina no dia (ver TimelineMaquinaService).
     *
     * @param  array<int, array{inicio: Carbon, fim: Carbon}>  $janelas
     * @return array<int, array{tipo: string, inicio: Carbon, fim: Carbon}>
     */
    public function segmentarFaseNoDia(Apontamento $apontamento, string $fase, array $janelas, Carbon $agora): array
    {
        $inicio = $fase === 'setup' ? $apontamento->setup_inicio : $apontamento->producao_inicio;

        if (! $inicio) {
            return [];
        }

        $fim = ($fase === 'setup' ? $apontamento->setup_fim : $apontamento->producao_fim) ?? $agora;

        $diaInicio = $janelas[0]['inicio'];
        $diaFim = $janelas[array_key_last($janelas)]['fim'];

        if ($fim->lessThanOrEqualTo($diaInicio) || $inicio->greaterThanOrEqualTo($diaFim)) {
            return [];
        }

        $pausasFase = $apontamento->pausas->where('fase', $fase);
        $ativos = $this->subtrairIntervalos($inicio, $fim, $pausasFase, $agora);

        $segmentos = [];

        foreach ($ativos as $intervalo) {
            foreach ($this->clipIntervaloComJanelas($intervalo['inicio'], $intervalo['fim'], $janelas) as $clip) {
                $segmentos[] = ['tipo' => $fase, 'inicio' => $clip['inicio'], 'fim' => $clip['fim']];
            }
        }

        return [...$segmentos, ...$this->segmentarPausasAvulsas($pausasFase, $janelas, $agora)];
    }

    /**
     * Segmentos de pausa de uma fase sem janela de trabalho ativa própria (ex.:
     * "aguardando"), recortados pelas janelas úteis do turno — usado na timeline
     * da máquina para que essas pausas apareçam como "pausa" em vez de "parado".
     *
     * @param  Collection<int, Pausa>  $pausas
     * @param  array<int, array{inicio: Carbon, fim: Carbon}>  $janelas
     * @return array<int, array{tipo: string, inicio: Carbon, fim: Carbon, motivo: string|null}>
     */
    public function segmentarPausasAvulsas(Collection $pausas, array $janelas, Carbon $agora): array
    {
        $segmentos = [];

        foreach ($pausas as $pausa) {
            $motivo = $pausa->motivoPausa?->nome;

            foreach ($this->clipIntervaloComJanelas($pausa->inicio, $pausa->fim ?? $agora, $janelas) as $clip) {
                $segmentos[] = ['tipo' => 'pausa', 'inicio' => $clip['inicio'], 'fim' => $clip['fim'], 'motivo' => $motivo];
            }
        }

        return $segmentos;
    }

    /**
     * Recorta [inicio, fim] pelas janelas úteis do turno, podendo produzir
     * mais de um pedaço quando o intervalo atravessa uma janela (ex.: um
     * apontamento que começou antes do almoço e terminou depois).
     *
     * @param  array<int, array{inicio: Carbon, fim: Carbon}>  $janelas
     * @return array<int, array{inicio: Carbon, fim: Carbon}>
     */
    private function clipIntervaloComJanelas(Carbon $inicio, Carbon $fim, array $janelas): array
    {
        $clipes = [];

        foreach ($janelas as $janela) {
            $clipInicio = $this->maior($inicio, $janela['inicio']);
            $clipFim = $this->menor($fim, $janela['fim']);

            if ($clipFim->greaterThan($clipInicio)) {
                $clipes[] = ['inicio' => $clipInicio->copy(), 'fim' => $clipFim->copy()];
            }
        }

        return $clipes;
    }

    /** @param array<int, array{inicio: Carbon, fim: Carbon}> $janelas */
    public function somaDuracaoJanelas(array $janelas): int
    {
        return array_sum(array_map(
            fn (array $janela): int => (int) $janela['inicio']->diffInSeconds($janela['fim']),
            $janelas
        ));
    }

    /** @param array<int, array{inicio: Carbon, fim: Carbon}> $janelas */
    public function intersecaoComJanelas(Carbon $inicio, Carbon $fim, array $janelas): int
    {
        $segundos = 0;

        foreach ($janelas as $janela) {
            $segundos += $this->intersecaoSegundos($inicio, $fim, $janela['inicio'], $janela['fim']);
        }

        return $segundos;
    }

    /**
     * Remove os intervalos de pausa de [inicio, fim], retornando os
     * sub-intervalos em que o apontamento esteve efetivamente ativo.
     *
     * @param  Collection<int, Pausa>  $pausas
     * @return array<int, array{inicio: Carbon, fim: Carbon}>
     */
    public function subtrairIntervalos(Carbon $inicio, Carbon $fim, Collection $pausas, Carbon $agora): array
    {
        $intervalosPausa = $pausas
            ->map(fn (Pausa $pausa): array => [
                'inicio' => $this->maior($pausa->inicio, $inicio),
                'fim' => $this->menor($pausa->fim ?? $agora, $fim),
            ])
            ->filter(fn (array $intervalo): bool => $intervalo['fim']->greaterThan($intervalo['inicio']))
            ->sortBy(fn (array $intervalo): int => $intervalo['inicio']->timestamp)
            ->values();

        $ativos = [];
        $cursor = $inicio->copy();

        foreach ($intervalosPausa as $intervalo) {
            if ($intervalo['inicio']->greaterThan($cursor)) {
                $ativos[] = ['inicio' => $cursor->copy(), 'fim' => $intervalo['inicio']->copy()];
            }

            if ($intervalo['fim']->greaterThan($cursor)) {
                $cursor = $intervalo['fim']->copy();
            }
        }

        if ($fim->greaterThan($cursor)) {
            $ativos[] = ['inicio' => $cursor->copy(), 'fim' => $fim->copy()];
        }

        return $ativos;
    }

    public function intersecaoSegundos(Carbon $aInicio, Carbon $aFim, Carbon $bInicio, Carbon $bFim): int
    {
        $inicio = $this->maior($aInicio, $bInicio);
        $fim = $this->menor($aFim, $bFim);

        return $fim->greaterThan($inicio) ? (int) $inicio->diffInSeconds($fim) : 0;
    }

    public function maior(Carbon $a, Carbon $b): Carbon
    {
        return $a->greaterThan($b) ? $a : $b;
    }

    public function menor(Carbon $a, Carbon $b): Carbon
    {
        return $a->lessThan($b) ? $a : $b;
    }
}
