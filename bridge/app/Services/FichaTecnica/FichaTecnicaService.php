<?php

declare(strict_types=1);

namespace App\Services\FichaTecnica;

use App\Exceptions\RegistroNaoEncontradoException;
use Illuminate\Support\Facades\DB;

class FichaTecnicaService implements FichaTecnicaServiceInterface
{
    public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array
    {
        // Barcode entrega '06854'; o banco armazena '6854' — remove zeros à esquerda
        $ordemLote = ltrim($ordemLote, '0') ?: '0';

        $rows = DB::select(
            'SELECT
                Empresa, Lote, DataEmbalagem, Prod_Codi, CodiSemiAcabado,
                DenoSemiAcabado, SubgSemiAcabado, TipoMate, Espess, Comp,
                Larg, QtdBorComp, QtdBorLarg, Pintura, CorCopo,
                Qtde_Prod, QtdeSemi, Qtde_Total
             FROM [db1Fabri].[dbo].[FbmLoteFichaTecnica]
             WHERE CodiSemiAcabado = ? AND Lote = ?',
            [$codPeca, $ordemLote]
        );

        if (empty($rows)) {
            throw new RegistroNaoEncontradoException(
                "Produto '{$codPeca}' não encontrado no lote '{$ordemLote}'."
            );
        }

        // Dados descritivos vêm da primeira linha; qtde_total é a soma de todas as linhas
        $first     = (array) $rows[0];
        $qtdeTotal = (int) array_sum(
            array_column(array_map(fn ($r) => (array) $r, $rows), 'Qtde_Total')
        );

        return $this->mapear($first, $qtdeTotal);
    }

    public function buscarFtecPecaPilha(string $codPeca): ?int
    {
        $row = DB::selectOne(
            'SELECT TOP 1 FtecpecaPilha FROM [db1Fabri].[dbo].[FbmFichatecnica] WHERE CodiSemiAcabado = ?',
            [$codPeca]
        );

        if (! $row) {
            return null;
        }

        $valor = (array) $row;

        return isset($valor['FtecpecaPilha']) && $valor['FtecpecaPilha'] > 0
            ? (int) $valor['FtecpecaPilha']
            : null;
    }

    public function contarFichasLote(string $ordemLote, string $codPeca): int
    {
        $ordemLote = ltrim($ordemLote, '0') ?: '0';

        $result = DB::selectOne(
            'SELECT COUNT(*) as total FROM [db1Fabri].[dbo].[FbmLoteFichaTecnica]
             WHERE CodiSemiAcabado = ? AND Lote = ?',
            [$codPeca, $ordemLote]
        );

        $total = (int) (((array) $result)['total'] ?? 0);

        return max(1, $total);
    }

    public function buscarTotaisPorPrefixoLote(string $ordemLote, string $prefixoCod): array
    {
        $ordemLote = ltrim($ordemLote, '0') ?: '0';

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total_pilhas, SUM(Qtde_Total) AS qtde_total
             FROM [db1Fabri].[dbo].[FbmLoteFichaTecnica]
             WHERE SUBSTRING(CodiSemiAcabado, 1, 5) = ? AND Lote = ?',
            [$prefixoCod, $ordemLote]
        );

        $row         = (array) $result;
        $totalPilhas = (int) ($row['total_pilhas'] ?? 0);
        $qtdeTotal   = $totalPilhas > 0 ? (int) ($row['qtde_total'] ?? 0) : null;

        return [
            'qtde_total'   => $qtdeTotal,
            'total_pilhas' => $totalPilhas,
        ];
    }

    private function mapear(array $row, int $qtdeTotal): array
    {
        return [
            'lote'              => $row['Lote'],
            'cod_produto'       => (string) ($row['Prod_Codi'] ?? ''),
            'cod_peca'          => (string) ($row['CodiSemiAcabado'] ?? ''),
            'desc_peca'         => (string) ($row['DenoSemiAcabado'] ?? ''),
            'qtde_total'        => $qtdeTotal,
            'empresa'           => $row['Empresa'] ?? null,
            'data_embalagem'    => $row['DataEmbalagem'] ?? null,
            'subg_semi_acabado' => $row['SubgSemiAcabado'] ?? null,
            'tipo_mate'         => $row['TipoMate'] ?? null,
            'espess'            => $row['Espess'] ?? null,
            'comp'              => $row['Comp'] ?? null,
            'larg'              => $row['Larg'] ?? null,
            'qtd_bor_comp'      => $row['QtdBorComp'] ?? null,
            'qtd_bor_larg'      => $row['QtdBorLarg'] ?? null,
            'pintura'           => $row['Pintura'] ?? null,
            'cor_copo'          => $row['CorCopo'] ?? null,
            'qtde_prod'         => isset($row['Qtde_Prod']) ? (int) $row['Qtde_Prod'] : null,
            'qtde_semi'         => isset($row['QtdeSemi']) ? (int) $row['QtdeSemi'] : null,
        ];
    }
}
