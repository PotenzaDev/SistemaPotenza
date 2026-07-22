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

    /**
     * Valida o cod_produto+cor lidos do código de barras contra o cadastro
     * de produtos do ERP (view Produto_Cadastro) e confirma que esse produto
     * de fato pertence à ficha (CodiSemiAcabado+Lote) sendo bipada em
     * FbmLoteFichaTecnica — é o que permite diferenciar, dentro do mesmo
     * lote/peça, qual ficha física corresponde a qual produto/cor exatos.
     *
     * @return array{cod_produto: string, cor_codigo: string, desc_produto: string, desc_cor: string}
     *
     * @throws \App\Exceptions\BusinessException quando o produto/cor não existe no cadastro (422),
     *                                            quando não corresponde a nenhuma ficha do lote (422),
     *                                            ou quando o SQL Server legado está inacessível (503).
     */
    public function buscarProdutoCompativel(
        string $codPeca,
        string $ordemLote,
        string $codProduto,
        string $corCodigo,
    ): array;

    /**
     * Detalha, um por um, todos os cod_peca (peça/produto) do LOTE inteiro —
     * ao contrário de buscarVariantesPorPrefixoLote(), que restringe às
     * variantes de um mesmo prefixo de 5 dígitos. Usado pelo apontamento de
     * corte (por lote), onde a seccionadora pode cortar peças de produtos
     * completamente diferentes dentro do mesmo lote físico. Em caso de falha
     * no SQL Server legado retorna [] (fallback seguro).
     *
     * @return array<int, array{cod_peca: string, desc_peca: string, qtde_total: int, total_pilhas: int}>
     */
    public function buscarFichasDoLote(string $ordemLote): array;
}
