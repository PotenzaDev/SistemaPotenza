<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EtapaFluxo;
use App\Repositories\Contracts\HistoricoLoteRepositoryInterface;
use Illuminate\Support\Collection;

class KanbanService
{
    public function __construct(
        private readonly HistoricoLoteRepositoryInterface $historicoRepo,
    ) {}

    public function kanban(): Collection
    {
        return EtapaFluxo::where('ativa', true)
            ->orderBy('ordem')
            ->with(['historicoLotes' => fn ($q) => $q->where('status', 'em_andamento')])
            ->get()
            ->map(fn (EtapaFluxo $etapa) => [
                'id'    => $etapa->id,
                'nome'  => $etapa->nome,
                'ordem' => $etapa->ordem,
                'lotes' => $etapa->historicoLotes->map(fn ($h) => $this->formatarLote($h)),
            ]);
    }

    public function lotesEtapa(int $etapaFluxoId): Collection
    {
        return $this->historicoRepo->porEtapa($etapaFluxoId)
            ->map(fn ($h) => $this->formatarLote($h));
    }

    public function historicoLote(string $ordemLote): Collection
    {
        return $this->historicoRepo->historicoCompleto($ordemLote)
            ->map(fn ($h) => [
                ...$this->formatarLote($h),
                'etapa_nome' => $h->etapaFluxo->nome,
                'saida'      => $h->saida?->toIso8601String(),
            ]);
    }

    private function formatarLote(mixed $h): array
    {
        return [
            'id'                => $h->id,
            'ordem_lote'        => $h->ordem_lote,
            'cod_peca'          => $h->cod_peca,
            'total_pilhas'      => $h->total_pilhas,
            'pilhas_concluidas' => $h->pilhas_concluidas,
            'percentual'        => $h->percentualConcluido(),
            'status'            => $h->status,
            'entrada'           => $h->entrada?->toIso8601String(),
        ];
    }
}
