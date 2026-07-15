<?php

declare(strict_types=1);

namespace App\Services\Produto;

use App\Exceptions\BusinessException;
use App\Models\Produto;
use App\Models\ProdutoPeca;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProdutoImportService implements ProdutoImportServiceInterface
{
    public function buscarNoErp(string $empresa, ?string $nome, ?string $subGrupo): array
    {
        $filtroNome = $this->paraFiltroLike($nome);
        $filtroSubGrupo = $this->paraFiltroLike($subGrupo);

        $rows = $this->select(
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
            [$empresa, $filtroNome, $filtroSubGrupo, $this->dataCorte()],
            'Falha ao consultar produtos no ERP.'
        );

        $produtosErp = array_map(fn ($row) => $this->mapearProduto((array) $row), $rows);

        $codigosImportados = Produto::query()
            ->where('empresa', $empresa)
            ->whereIn('cod_produto', array_column($produtosErp, 'cod_produto'))
            ->pluck('cod_produto')
            ->all();

        return array_map(
            fn (array $produtoErp) => $produtoErp + [
                'ja_importado' => in_array($produtoErp['cod_produto'], $codigosImportados, true),
            ],
            $produtosErp
        );
    }

    public function buscarSubGruposNoErp(string $empresa): array
    {
        $rows = $this->select(
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
            [$empresa, $this->dataCorte()],
            'Falha ao consultar sub-grupos de produtos no ERP.'
        );

        return array_map(fn ($row) => (string) ((array) $row)['Prod_Sub_Grupo'], $rows);
    }

    public function importar(array $dadosProdutoErp): Produto
    {
        $codProduto = (string) $dadosProdutoErp['cod_produto'];

        // Nota: não filtra por Empresa — a tabela FbmLoteFichaTecnica é sempre
        // da mesma empresa.
        $rows = $this->select(
            'SELECT DISTINCT
                CodiSemiAcabado, DenoSemiAcabado, SubgSemiAcabado, TipoMate, Espess, Comp, Larg
             FROM [db1Fabri].[dbo].[FbmLoteFichaTecnica]
             WHERE Prod_Codi = ?
               AND TipoMate IS NOT NULL
               AND TipoMate <> \'\'
             ORDER BY CodiSemiAcabado',
            [$codProduto],
            'Falha ao consultar peças do produto no ERP.'
        );

        $pecasErp = array_map(fn ($row) => $this->mapearPeca((array) $row), $rows);

        return DB::transaction(function () use ($dadosProdutoErp, $pecasErp) {
            $produto = Produto::updateOrCreate(
                ['cod_produto' => $dadosProdutoErp['cod_produto']],
                [
                    'nome' => $dadosProdutoErp['nome'],
                    'grupo' => $dadosProdutoErp['grupo'] ?? null,
                    'sub_grupo' => $dadosProdutoErp['sub_grupo'] ?? null,
                    'empresa' => $dadosProdutoErp['empresa'],
                    'ativo' => true,
                ]
            );

            foreach ($pecasErp as $i => $peca) {
                $numero = ProdutoImportTransform::numeroSemi($peca['cod_peca'] ?? null, $i + 1);
                $dimensao = ProdutoImportTransform::dimensao(
                    $peca['comprimento'] ?? null,
                    $peca['largura'] ?? null,
                    $peca['espessura'] ?? null
                );

                $existente = ProdutoPeca::where('produto_id', $produto->id)
                    ->where('numero', $numero)
                    ->first();

                if (! $existente) {
                    ProdutoPeca::create([
                        'produto_id' => $produto->id,
                        'numero' => $numero,
                        'nome' => $peca['nome'] ?? '',
                        'sub_grupo' => $peca['sub_grupo'] ?? null,
                        'dimensao' => $dimensao,
                        'material' => $peca['tipo_mate'] ?? null,
                        'ordem' => $numero,
                    ]);
                } else {
                    $existente->update([
                        'sub_grupo' => $peca['sub_grupo'] ?? null,
                        'dimensao' => $dimensao,
                        'material' => $peca['tipo_mate'] ?? null,
                    ]);
                }
            }

            return $produto->load('pecas');
        });
    }

    private function dataCorte(): string
    {
        return now()->subMonths(12)->toDateString();
    }

    private function paraFiltroLike(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '%';
        }

        $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $valor);

        return '%'.$escapado.'%';
    }

    private function mapearProduto(array $row): array
    {
        // Prod_Codi é CHAR de largura fixa no SQL Server e costuma vir com
        // espaços à direita — sem o trim, a comparação de "já importado"
        // (por cod_produto) nunca bate.
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

    private function select(string $query, array $bindings, string $mensagemErro): array
    {
        try {
            return DB::connection('sqlsrv_legado')->select($query, $bindings);
        } catch (QueryException) {
            throw new BusinessException($mensagemErro, 503);
        }
    }
}
