<?php

declare(strict_types=1);

namespace App\Services\Produto;

use Illuminate\Support\Facades\DB;

class ProdutoService implements ProdutoServiceInterface
{
    public function listar(string $empresa, ?string $nome, ?string $subGrupo, string $dataCorte): array
    {
        $filtroNome = $this->paraFiltroLike($nome);
        $filtroSubGrupo = $this->paraFiltroLike($subGrupo);

        $rows = DB::select(
            'SELECT Prod_Codi, Prod_Deno, Prod_Grupo, Prod_Sub_Grupo
             FROM [db1Fabri].[dbo].[Produto_Cadastro] p
             WHERE Empresa        = ?
               AND Prod_Tipo      = \'P\'
               AND Prod_Deno      LIKE ? ESCAPE \'\\\'
               AND Prod_Sub_Grupo LIKE ? ESCAPE \'\\\'
               AND EXISTS (
                   SELECT 1 FROM [db1Fabri].[dbo].[FbmLoteFichaTecnica] f
                   WHERE f.Prod_Codi = p.Prod_Codi
                     AND f.DataEmbalagem > ?
               )
             ORDER BY Prod_Sub_Grupo, Prod_Codi',
            [$empresa, $filtroNome, $filtroSubGrupo, $dataCorte]
        );

        return array_map(fn ($row) => $this->mapear((array) $row), $rows);
    }

    public function listarSubGrupos(string $empresa, string $dataCorte): array
    {
        $rows = DB::select(
            'SELECT DISTINCT Prod_Sub_Grupo
             FROM [db1Fabri].[dbo].[Produto_Cadastro] p
             WHERE Empresa        = ?
               AND Prod_Tipo      = \'P\'
               AND Prod_Sub_Grupo IS NOT NULL
               AND Prod_Sub_Grupo <> \'\'
               AND EXISTS (
                   SELECT 1 FROM [db1Fabri].[dbo].[FbmLoteFichaTecnica] f
                   WHERE f.Prod_Codi = p.Prod_Codi
                     AND f.DataEmbalagem > ?
               )
             ORDER BY Prod_Sub_Grupo',
            [$empresa, $dataCorte]
        );

        return array_map(fn ($row) => (string) ((array) $row)['Prod_Sub_Grupo'], $rows);
    }

    public function buscarPecas(string $codProduto): array
    {
        // Nota: não filtra por Empresa — a tabela FbmLoteFichaTecnica é sempre
        // da mesma empresa.
        $rows = DB::select(
            'SELECT DISTINCT
                CodiSemiAcabado, DenoSemiAcabado, SubgSemiAcabado, TipoMate, Espess, Comp, Larg
             FROM [db1Fabri].[dbo].[FbmLoteFichaTecnica]
             WHERE Prod_Codi = ?
               AND TipoMate IS NOT NULL
               AND TipoMate <> \'\'
             ORDER BY CodiSemiAcabado',
            [$codProduto]
        );

        return array_map(fn ($row) => $this->mapearPeca((array) $row), $rows);
    }

    private function paraFiltroLike(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '%';
        }

        $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $valor);

        return '%'.$escapado.'%';
    }

    private function mapear(array $row): array
    {
        // Prod_Codi é CHAR de largura fixa no SQL Server e costuma vir com
        // espaços à direita — sem o trim, a comparação de "já importado" no
        // backend (por cod_produto) nunca bate (mesma causa raiz já corrigida
        // para CodiSemiAcabado/DenoSemiAcabado em FichaTecnicaService).
        return [
            'cod_produto' => trim((string) ($row['Prod_Codi'] ?? '')),
            'nome' => $row['Prod_Deno'] ?? null,
            'grupo' => $row['Prod_Grupo'] ?? null,
            'sub_grupo' => $row['Prod_Sub_Grupo'] ?? null,
        ];
    }

    private function mapearPeca(array $row): array
    {
        return [
            'cod_peca' => (string) ($row['CodiSemiAcabado'] ?? ''),
            'nome' => $row['DenoSemiAcabado'] ?? null,
            'sub_grupo' => $row['SubgSemiAcabado'] ?? null,
            'tipo_mate' => $row['TipoMate'] ?? null,
            'espessura' => $row['Espess'] ?? null,
            'comprimento' => $row['Comp'] ?? null,
            'largura' => $row['Larg'] ?? null,
        ];
    }
}
