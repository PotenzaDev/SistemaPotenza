<?php

declare(strict_types=1);

namespace App\Services\Produto;

interface ProdutoServiceInterface
{
    /**
     * Lista produtos acabados (Prod_Tipo = 'P') com pelo menos um lote de
     * ficha técnica embalado após a data de corte informada.
     *
     * @param  string  $empresa  'FBM' ou 'FBP'.
     * @param  string|null  $nome  Filtro parcial (LIKE) por Prod_Deno; null/vazio = sem filtro.
     * @param  string|null  $subGrupo  Filtro parcial (LIKE) por Prod_Sub_Grupo; null/vazio = sem filtro.
     * @param  string  $dataCorte  Data (Y-m-d) já calculada pelo caller.
     * @return array<int, array{cod_produto: string, nome: mixed, grupo: mixed, sub_grupo: mixed}>
     */
    public function listar(string $empresa, ?string $nome, ?string $subGrupo, string $dataCorte): array;

    /**
     * Lista os sub-grupos distintos (não nulos/vazios) de produtos acabados
     * da empresa informada, com pelo menos um lote de ficha técnica embalado
     * após a data de corte.
     *
     * @param  string  $empresa  'FBM' ou 'FBP'.
     * @param  string  $dataCorte  Data (Y-m-d) já calculada pelo caller.
     * @return array<int, string>
     */
    public function listarSubGrupos(string $empresa, string $dataCorte): array;

    /**
     * Busca as peças (semi-acabados) distintas da ficha técnica de um produto.
     *
     * @param  string  $codProduto  Prod_Codi do produto.
     * @return array<int, array{cod_peca: string, nome: mixed, sub_grupo: mixed, tipo_mate: mixed, espessura: mixed, comprimento: mixed, largura: mixed}>
     */
    public function buscarPecas(string $codProduto): array;
}
