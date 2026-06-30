<?php

declare(strict_types=1);

namespace App\Services\FichaTecnica;

use App\Exceptions\RegistroNaoEncontradoException;

/**
 * Implementação sem dependência do SQL Server, usada em ambiente local/testes
 * quando DB_HOST não está configurado.
 */
class FakeFichaTecnicaService implements FichaTecnicaServiceInterface
{
    public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array
    {
        if ($codPeca === '0000000') {
            throw new RegistroNaoEncontradoException(
                "Produto '{$codPeca}' não encontrado no lote '{$ordemLote}'."
            );
        }

        return [
            'lote'              => ltrim($ordemLote, '0') ?: '0',
            'cod_produto'       => 'PROD-' . $ordemLote,
            'cod_peca'          => $codPeca,
            'desc_peca'         => 'Peça Fake ' . $ordemLote,
            'qtde_total'        => 300,
            'empresa'           => '01',
            'data_embalagem'    => now()->format('Y-m-d'),
            'subg_semi_acabado' => null,
            'tipo_mate'         => null,
            'espess'            => null,
            'comp'              => null,
            'larg'              => null,
            'qtd_bor_comp'      => null,
            'qtd_bor_larg'      => null,
            'pintura'           => null,
            'cor_copo'          => null,
            'qtde_prod'         => 300,
            'qtde_semi'         => 300,
        ];
    }

    public function buscarFtecPecaPilha(string $codPeca): ?int
    {
        return $codPeca === '0000000' ? null : 50;
    }

    public function contarFichasLote(string $ordemLote, string $codPeca): int
    {
        return 1; // comportamento padrão: 1 ficha por pilha
    }

    public function buscarTotaisPorPrefixoLote(string $ordemLote, string $prefixoCod): array
    {
        return [
            'qtde_total'   => 300,
            'total_pilhas' => 6,
        ];
    }
}
