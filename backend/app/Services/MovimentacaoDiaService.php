<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Apontamento;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Verifica se existiu movimentação real (setup, produção ou ficha bipada)
 * em um intervalo de tempo — usado para decidir se um dia sem turno
 * configurado (ex.: sábado, feriado) ainda assim deve aparecer nos
 * relatórios de um único dia (ver RelatorioProducaoService::relatorioPorDia
 * e TimelineMaquinaService::timelineDoDia).
 */
class MovimentacaoDiaService
{
    public function existeParaSessao(Carbon $inicioDia, Carbon $fimDia, ?int $operarioId = null, ?int $maquinaId = null): bool
    {
        return $this->existe($inicioDia, $fimDia, function (Builder $query) use ($operarioId, $maquinaId) {
            $query->withTrashed()
                ->when($operarioId, fn ($q) => $q->where('operario_id', $operarioId))
                ->when($maquinaId, fn ($q) => $q->where('maquina_id', $maquinaId));
        });
    }

    /** @param  iterable<int>  $maquinaIds */
    public function existeParaMaquinas(Carbon $inicioDia, Carbon $fimDia, iterable $maquinaIds): bool
    {
        $ids = collect($maquinaIds)->all();

        if (empty($ids)) {
            return false;
        }

        return $this->existe($inicioDia, $fimDia, function (Builder $query) use ($ids) {
            $query->withTrashed()->whereIn('maquina_id', $ids);
        });
    }

    private function existe(Carbon $inicioDia, Carbon $fimDia, Closure $filtroSessao): bool
    {
        $apontamentosQuery = Apontamento::query()->whereHas('sessaoTrabalho', $filtroSessao);

        $temFaseNoDia = (clone $apontamentosQuery)
            ->where(function (Builder $query) use ($inicioDia, $fimDia) {
                $query->where(function (Builder $q) use ($inicioDia, $fimDia) {
                    $q->whereNotNull('setup_inicio')
                        ->where('setup_inicio', '<=', $fimDia)
                        ->where(fn (Builder $qq) => $qq->whereNull('setup_fim')->orWhere('setup_fim', '>=', $inicioDia));
                })->orWhere(function (Builder $q) use ($inicioDia, $fimDia) {
                    $q->whereNotNull('producao_inicio')
                        ->where('producao_inicio', '<=', $fimDia)
                        ->where(fn (Builder $qq) => $qq->whereNull('producao_fim')->orWhere('producao_fim', '>=', $inicioDia));
                });
            })
            ->exists();

        if ($temFaseNoDia) {
            return true;
        }

        return (clone $apontamentosQuery)
            ->whereHas('fichas', function (Builder $query) use ($inicioDia, $fimDia) {
                $query->whereBetween('bipada_at', [$inicioDia, $fimDia]);
            })
            ->exists();
    }
}
