<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Apontamento;
use App\Models\SessaoTrabalho;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ApontamentoRepository implements ApontamentoRepositoryInterface
{
    private const EAGER = ['etapaFluxo', 'fichas', 'pausas.motivoPausa'];

    public function criar(array $dados): Apontamento
    {
        return Apontamento::create($dados)->load(self::EAGER);
    }

    public function buscarPorId(int $id): ?Apontamento
    {
        return Apontamento::with(self::EAGER)->find($id);
    }

    public function buscarApontamentoAtivo(SessaoTrabalho $sessao): ?Apontamento
    {
        return Apontamento::where('sessao_trabalho_id', $sessao->id)
            ->whereIn('status', [
                Apontamento::STATUS_EM_SETUP,
                Apontamento::STATUS_AGUARDANDO_PRODUCAO,
                Apontamento::STATUS_EM_PRODUCAO,
                Apontamento::STATUS_EM_PAUSA_SETUP,
                Apontamento::STATUS_EM_PAUSA_PRODUCAO,
            ])
            ->with(self::EAGER)
            ->first();
    }

    public function somarQtdProduzida(int $etapaFluxoId, string $ordemLote): int
    {
        return (int) Apontamento::where('etapa_fluxo_id', $etapaFluxoId)
            ->where('ordem_lote', $ordemLote)
            ->where('status', 'finalizado')
            ->sum('qtd_produzida');
    }

    /**
     * Apontamentos relevantes para o período informado, ou — na ausência de
     * filtros de data — os de hoje (iniciados hoje OU ainda em aberto, já que
     * uma produção pode atravessar a virada do dia e terminar amanhã).
     *
     * Filtros aceitos (todos opcionais): data_inicio, data_fim (Y-m-d),
     * operario_id, maquina_id, grupo_id, ordem_lote.
     */
    public function apontamentosDoDia(array $filtros = []): Collection
    {
        $dataInicio = $filtros['data_inicio'] ?? null;
        $dataFim    = $filtros['data_fim'] ?? null;

        $query = Apontamento::query();

        if ($dataInicio || $dataFim) {
            $query->whereDate('created_at', '>=', $dataInicio ?? $dataFim)
                ->whereDate('created_at', '<=', $dataFim ?? $dataInicio);
        } else {
            $hoje = Carbon::today();

            $query->where(function ($q) use ($hoje) {
                $q->whereDate('created_at', $hoje)
                    ->orWhereIn('status', [
                        Apontamento::STATUS_EM_SETUP,
                        Apontamento::STATUS_AGUARDANDO_PRODUCAO,
                        Apontamento::STATUS_EM_PRODUCAO,
                        Apontamento::STATUS_EM_PAUSA_SETUP,
                        Apontamento::STATUS_EM_PAUSA_PRODUCAO,
                    ]);
            });
        }

        if (! empty($filtros['operario_id']) || ! empty($filtros['maquina_id']) || ! empty($filtros['grupo_id'])) {
            $query->whereHas('sessaoTrabalho', function ($q) use ($filtros) {
                if (! empty($filtros['operario_id'])) {
                    $q->where('operario_id', $filtros['operario_id']);
                }
                if (! empty($filtros['maquina_id'])) {
                    $q->where('maquina_id', $filtros['maquina_id']);
                }
                if (! empty($filtros['grupo_id'])) {
                    $q->whereHas('maquina', fn ($mq) => $mq->where('etapa_fluxo_id', $filtros['grupo_id']));
                }
            });
        }

        if (! empty($filtros['ordem_lote'])) {
            $query->where('ordem_lote', 'like', '%' . $filtros['ordem_lote'] . '%');
        }

        return $query
            ->with(['sessaoTrabalho.operario.user', 'sessaoTrabalho.maquina.etapaFluxo', 'fichas', 'pausas'])
            ->latest()
            ->get();
    }

    public function historicoPorOperario(int $operarioId): Collection
    {
        return Apontamento::whereHas('sessaoTrabalho', fn ($q) => $q->where('operario_id', $operarioId))
            ->with(self::EAGER)
            ->latest()
            ->get();
    }

    public function buscarApontamentoPendentePorMaquina(int $maquinaId, int $operarioId): ?Apontamento
    {
        return Apontamento::whereIn('status', [
            Apontamento::STATUS_EM_PAUSA_SETUP,
            Apontamento::STATUS_EM_PAUSA_PRODUCAO,
        ])
            ->where('created_at', '>=', Carbon::now()->subDays(3))
            ->whereHas('sessaoTrabalho', fn ($q) => $q
                ->where('maquina_id', $maquinaId)
                ->where('operario_id', $operarioId)
                ->whereNotNull('fim')
            )
            ->whereHas('pausas', fn ($q) => $q->whereNull('fim'))
            ->with(self::EAGER)
            ->first();
    }

    public function atualizarSessao(Apontamento $apontamento, int $sessaoId): Apontamento
    {
        $apontamento->update(['sessao_trabalho_id' => $sessaoId]);

        return $apontamento->load(self::EAGER);
    }
}
