<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Apontamento;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Verifica se existiu movimentação real (setup, produção ou ficha bipada)
 * em um intervalo de tempo — usado para decidir se um dia sem turno
 * configurado (ex.: sábado, feriado) ainda assim deve aparecer nos
 * relatórios de um único dia (ver RelatorioProducaoService::relatorioPorDia
 * e TimelineMaquinaService::timelineDoDia), e para excluir de dashboards e
 * relatórios as máquinas que não trabalharam no dia/período consultado.
 */
class MovimentacaoDiaService
{
    public function existeParaSessao(Carbon $inicioDia, Carbon $fimDia, ?int $operarioId = null, ?int $maquinaId = null): bool
    {
        return $this->apontamentosNoDia($inicioDia, $fimDia, function (Builder $query) use ($operarioId, $maquinaId) {
            $query->withTrashed()
                ->when($operarioId, fn ($q) => $q->where('operario_id', $operarioId))
                ->when($maquinaId, fn ($q) => $q->where('maquina_id', $maquinaId));
        })->exists();
    }

    /** @param  iterable<int>  $maquinaIds */
    public function existeParaMaquinas(Carbon $inicioDia, Carbon $fimDia, iterable $maquinaIds): bool
    {
        $ids = collect($maquinaIds)->all();

        if (empty($ids)) {
            return false;
        }

        return $this->apontamentosNoDia($inicioDia, $fimDia, function (Builder $query) use ($ids) {
            $query->withTrashed()->whereIn('maquina_id', $ids);
        })->exists();
    }

    /**
     * IDs (dentre os informados) de máquina que tiveram alguma movimentação
     * real sobrepondo [inicioDia, fimDia] — usado para excluir, de
     * dashboards e relatórios, máquinas que não trabalharam no dia/período
     * (ver DashboardService::estadoMaquinas,
     * RelatorioProducaoService::relatorioMaquinasPorPeriodo e
     * TimelineMaquinaService::timelineDoDia).
     *
     * @param  iterable<int>  $maquinaIds
     * @return Collection<int, int>
     */
    public function idsMaquinasComMovimentacao(Carbon $inicioDia, Carbon $fimDia, iterable $maquinaIds): Collection
    {
        $ids = collect($maquinaIds)->all();

        if (empty($ids)) {
            return collect();
        }

        return $this->apontamentosNoDia($inicioDia, $fimDia, function (Builder $query) use ($ids) {
            $query->withTrashed()->whereIn('maquina_id', $ids);
        })
            ->with('sessaoTrabalho:id,maquina_id')
            ->get()
            ->pluck('sessaoTrabalho.maquina_id')
            ->unique()
            ->values();
    }

    private function apontamentosNoDia(Carbon $inicioDia, Carbon $fimDia, Closure $filtroSessao): Builder
    {
        return Apontamento::query()
            ->whereHas('sessaoTrabalho', $filtroSessao)
            ->where(function (Builder $query) use ($inicioDia, $fimDia) {
                $query->where(function (Builder $q) use ($inicioDia, $fimDia) {
                    $q->whereNotNull('setup_inicio')
                        ->where('setup_inicio', '<=', $fimDia)
                        ->where(fn (Builder $qq) => $qq->whereNull('setup_fim')->orWhere('setup_fim', '>=', $inicioDia));
                })->orWhere(function (Builder $q) use ($inicioDia, $fimDia) {
                    $q->whereNotNull('producao_inicio')
                        ->where('producao_inicio', '<=', $fimDia)
                        ->where(fn (Builder $qq) => $qq->whereNull('producao_fim')->orWhere('producao_fim', '>=', $inicioDia));
                })->orWhereHas('fichas', function (Builder $query) use ($inicioDia, $fimDia) {
                    $query->whereBetween('bipada_at', [$inicioDia, $fimDia]);
                });
            });
    }
}
