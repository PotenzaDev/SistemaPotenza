<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\FichaApontamento;
use Illuminate\Database\Eloquent\Collection;

interface FichaApontamentoRepositoryInterface
{
    public function criar(array $dados): FichaApontamento;

    public function fichasDoApontamento(int $apontamentoId): Collection;

    /** Últimas fichas bipadas pelo operário no mesmo setor (etapa do fluxo). */
    public function fichasRecentesDoOperario(int $operarioId, int $etapaFluxoId, int $limit = 30): Collection;

    /** Verifica se a pilha já foi bipada em qualquer apontamento do mesmo lote/peça/etapa. */
    public function pilhaJaBipada(string $ordemLote, string $codPeca, int $etapaFluxoId, int $pilha): bool;

    /** Conta quantas fichas (total, não distintas) já foram bipadas para o lote nesta etapa. */
    public function contarPilhasBipadasDoLote(string $ordemLote, string $codPeca, int $etapaFluxoId): int;

    /**
     * Conta quantas vezes uma pilha específica foi bipada em qualquer apontamento
     * do mesmo lote/peça/etapa. Usado para comparar contra o total de fichas
     * permitidas para essa pilha (retornado pela bridge).
     */
    public function contarVezesPilhaBipada(string $ordemLote, string $codPeca, int $etapaFluxoId, int $pilha): int;

    public function atualizarQtdProduzida(int $fichaId, int $qtdProduzida): FichaApontamento;

    /** Fecha o tempo de produção da ficha: grava fim_producao e duracao_segundos. */
    public function fecharFicha(int $fichaId, \Carbon\Carbon $fim): FichaApontamento;
}
