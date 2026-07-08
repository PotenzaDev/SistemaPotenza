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
     * Conta quantas vezes uma pilha específica foi bipada dentro do MESMO
     * apontamento informado. Usado para detectar bipagem duplicada acidental
     * (mesma ficha lida duas vezes na mesma produção), comparada contra o
     * total de fichas físicas permitidas para essa pilha (retornado pela bridge).
     */
    public function contarVezesPilhaBipadaNoApontamento(int $apontamentoId, string $codPeca, int $pilha): int;

    /**
     * Conta quantas vezes uma pilha específica foi bipada em OUTROS apontamentos
     * (diferentes do informado) do mesmo lote/peça/etapa. Usado para detectar
     * repasse legítimo da peça por uma passagem anterior já finalizada — não é
     * comparado contra o limite da bridge, apenas exige confirmação do operário.
     */
    public function contarVezesPilhaBipadaEmOutrosApontamentos(
        string $ordemLote,
        string $codPeca,
        int $etapaFluxoId,
        int $pilha,
        int $apontamentoIdAtual,
    ): int;

    public function atualizarQtdProduzida(int $fichaId, int $qtdProduzida): FichaApontamento;

    /**
     * Fecha o tempo de produção da ficha: grava fim_producao e duracao_segundos.
     * Quando $qtdProduzida é informado, grava também a quantidade produzida —
     * usado ao bipar a próxima ficha, para não deixar a ficha anterior sem
     * qtd_produzida registrada caso o apontamento nunca chegue a finalizar().
     */
    public function fecharFicha(int $fichaId, \Carbon\Carbon $fim, ?int $qtdProduzida = null): FichaApontamento;
}
