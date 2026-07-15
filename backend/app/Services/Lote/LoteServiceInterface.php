<?php

declare(strict_types=1);

namespace App\Services\Lote;

interface LoteServiceInterface
{
    /**
     * Busca os dados técnicos do lote filtrando por CodiSemiAcabado + Lote,
     * consultando diretamente o SQL Server legado (conexão sqlsrv_legado).
     *
     * @param  string $ordemLote  Código do lote (com ou sem zeros à esquerda).
     * @param  string $codPeca    CodiSemiAcabado vindo do código de barras.
     * @return array  lote, cod_produto, cod_peca, desc_peca, qtde_total e campos adicionais.
     *
     * @throws \App\Exceptions\BusinessException quando o produto não é encontrado no lote (422)
     *                                            ou o SQL Server legado está inacessível (503).
     */
    public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array;

    /**
     * Retorna a quantidade de peças por pilha (FtecpecaPilha) da ficha técnica
     * do produto, consultando diretamente o SQL Server legado. Retorna null se não encontrado.
     *
     * @throws \App\Exceptions\BusinessException quando o SQL Server legado está inacessível (503).
     */
    public function buscarFtecPecaPilha(string $codPeca): ?int;

    /**
     * Retorna a quantidade de fichas (linhas) em FbmLoteFichaTecnica
     * para o CodiSemiAcabado + Lote. Mínimo 1.
     *
     * @throws \App\Exceptions\BusinessException quando o SQL Server legado está inacessível (503).
     */
    public function contarFichasLote(string $ordemLote, string $codPeca): int;

    /**
     * Soma qtde_total e conta fichas (pilhas) de todos os produtos do lote
     * cujo cod_peca começa com os 5 dígitos informados.
     * Em caso de falha retorna fallback seguro: ['qtde_total' => null, 'total_pilhas' => 0].
     */
    public function buscarTotaisPorPrefixoLote(string $ordemLote, string $prefixoCod): array;

    /**
     * Detalha, um por um, todos os cod_peca (cor/variante) que compartilham
     * os 5 dígitos de prefixo informados no lote dado, cada um com sua
     * própria qtde_total exigida — ao contrário de buscarTotaisPorPrefixoLote(),
     * que soma tudo junto. Em caso de falha no SQL Server legado retorna [] (fallback seguro).
     *
     * @return array<int, array{cod_peca: string, desc_peca: string, qtde_total: int, total_pilhas: int}>
     */
    public function buscarVariantesPorPrefixoLote(string $ordemLote, string $prefixoCod): array;
}
