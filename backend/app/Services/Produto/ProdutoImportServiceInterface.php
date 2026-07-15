<?php

declare(strict_types=1);

namespace App\Services\Produto;

use App\Exceptions\BusinessException;
use App\Models\Produto;

interface ProdutoImportServiceInterface
{
    /**
     * Busca produtos no ERP legado (SQL Server, conexão sqlsrv_legado) filtrando
     * por empresa, nome e/ou sub-grupo, restrito a uma janela relativa de 12 meses.
     * Cada item é marcado com `ja_importado` indicando se já existe um
     * `Produto` local com o mesmo `cod_produto` + `empresa`.
     *
     * @return array<int, array{cod_produto: string, nome: string, grupo: string, sub_grupo: string, ja_importado: bool}>
     *
     * @throws BusinessException quando o SQL Server legado está inacessível (503).
     */
    public function buscarNoErp(string $empresa, ?string $nome, ?string $subGrupo): array;

    /**
     * Busca a lista de sub-grupos distintos disponíveis no ERP legado
     * para a empresa informada.
     *
     * @return array<int, string>
     *
     * @throws BusinessException quando o SQL Server legado está inacessível (503).
     */
    public function buscarSubGruposNoErp(string $empresa): array;

    /**
     * Importa (ou reimporta) um produto e suas peças a partir dos dados
     * do ERP legado, persistindo em `produtos` e `produto_pecas`.
     *
     * @param  array{cod_produto: string, nome: string, grupo?: ?string, sub_grupo?: ?string, empresa: string}  $dadosProdutoErp
     *
     * @throws BusinessException quando o SQL Server legado está inacessível (503).
     */
    public function importar(array $dadosProdutoErp): Produto;
}
