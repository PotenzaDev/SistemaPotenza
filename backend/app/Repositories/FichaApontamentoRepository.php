<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\FichaApontamento;
use App\Repositories\Contracts\FichaApontamentoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class FichaApontamentoRepository implements FichaApontamentoRepositoryInterface
{
    public function criar(array $dados): FichaApontamento
    {
        $dados['bipada_at'] ??= Carbon::now();

        return FichaApontamento::create($dados)->load('apontamento');
    }

    public function fichasDoApontamento(int $apontamentoId): Collection
    {
        return FichaApontamento::where('apontamento_id', $apontamentoId)
            ->orderBy('pilha')
            ->get();
    }

    public function fichasRecentesDoOperario(int $operarioId, int $etapaFluxoId, int $limit = 30): Collection
    {
        return FichaApontamento::query()
            ->whereHas('apontamento', function ($q) use ($operarioId, $etapaFluxoId) {
                $q->where('etapa_fluxo_id', $etapaFluxoId)
                    ->whereHas('sessaoTrabalho', fn ($sq) => $sq->where('operario_id', $operarioId));
            })
            ->with('apontamento:id,ordem_lote,cod_produto,qtde_total')
            ->orderByDesc('bipada_at')
            ->limit($limit)
            ->get();
    }

    public function pilhaJaBipada(string $ordemLote, string $codPeca, int $etapaFluxoId, int $pilha): bool
    {
        return FichaApontamento::where('pilha', $pilha)
            ->whereHas('apontamento', function ($q) use ($ordemLote, $codPeca, $etapaFluxoId) {
                $q->where('ordem_lote', $ordemLote)
                    ->where('cod_peca', $codPeca)
                    ->where('etapa_fluxo_id', $etapaFluxoId);
            })
            ->exists();
    }

    public function contarPilhasBipadasDoLote(string $ordemLote, string $codPeca, int $etapaFluxoId): int
    {
        return FichaApontamento::whereHas('apontamento', function ($q) use ($ordemLote, $codPeca, $etapaFluxoId) {
            $q->where('ordem_lote', $ordemLote)
                ->where('cod_peca', $codPeca)
                ->where('etapa_fluxo_id', $etapaFluxoId);
        })
        ->count();
    }

    public function contarVezesPilhaBipadaNoApontamento(int $apontamentoId, string $codPeca, int $pilha): int
    {
        return FichaApontamento::where('apontamento_id', $apontamentoId)
            ->where('pilha', $pilha)
            ->where('cod_peca', $codPeca)
            ->count();
    }

    public function contarVezesPilhaBipadaEmOutrosApontamentos(
        string $ordemLote,
        string $codPeca,
        int $etapaFluxoId,
        int $pilha,
        int $apontamentoIdAtual,
    ): int {
        return FichaApontamento::where('pilha', $pilha)
            ->where('cod_peca', $codPeca)
            ->where('apontamento_id', '!=', $apontamentoIdAtual)
            ->whereHas('apontamento', function ($q) use ($ordemLote, $etapaFluxoId) {
                $q->where('ordem_lote', $ordemLote)
                    ->where('etapa_fluxo_id', $etapaFluxoId);
            })
            ->count();
    }

    public function atualizarQtdProduzida(int $fichaId, int $qtdProduzida): FichaApontamento
    {
        $ficha = FichaApontamento::findOrFail($fichaId);
        $ficha->update(['qtd_produzida' => $qtdProduzida]);

        return $ficha->load('apontamento');
    }

    public function fecharFicha(int $fichaId, \Carbon\Carbon $fim): FichaApontamento
    {
        $ficha   = FichaApontamento::findOrFail($fichaId);
        $duracao = (int) $ficha->bipada_at->diffInSeconds($fim);
        $ficha->update(['fim_producao' => $fim, 'duracao_segundos' => $duracao]);

        return $ficha;
    }
}
