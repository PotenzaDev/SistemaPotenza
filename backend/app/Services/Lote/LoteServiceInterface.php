<?php

declare(strict_types=1);

namespace App\Services\Lote;

interface LoteServiceInterface
{
    /**
     * Busca os dados técnicos do lote filtrando por CodiSemiAcabado + Lote,
     * consultando a API Bridge (banco legado).
     *
     * @param  string $ordemLote  Código do lote (com ou sem zeros à esquerda).
     * @param  string $codPeca    CodiSemiAcabado vindo do código de barras.
     * @return array  lote, cod_produto, cod_peca, desc_peca, qtde_total e campos adicionais.
     *
     * @throws \App\Exceptions\BusinessException quando o produto não é encontrado no lote (422)
     *                                            ou a API Bridge está indisponível (503).
     */
    public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array;

    /**
     * Retorna a quantidade de peças por pilha (FtecpecaPilha) da ficha técnica
     * do produto, consultando a API Bridge (banco legado). Retorna null se não encontrado.
     *
     * @throws \App\Exceptions\BusinessException quando a API Bridge está indisponível (503).
     */
    public function buscarFtecPecaPilha(string $codPeca): ?int;

    /**
     * Retorna a quantidade de fichas (linhas) em FbmLoteFichaTecnica
     * para o CodiSemiAcabado + Lote. Mínimo 1.
     *
     * @throws \App\Exceptions\BusinessException quando a API Bridge está indisponível (503).
     */
    public function contarFichasLote(string $ordemLote, string $codPeca): int;
}
