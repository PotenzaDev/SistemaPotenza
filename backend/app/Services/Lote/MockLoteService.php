<?php

declare(strict_types=1);

namespace App\Services\Lote;

class MockLoteService implements LoteServiceInterface
{
    public function buscarPorOrdemLote(string $ordemLote, string $codPeca = ''): array
    {
        return [
            'lote'              => $ordemLote,
            'cod_produto'       => 'PROD-' . $ordemLote,
            'cod_peca'          => '9999999',
            'desc_peca'         => 'Peça Mock ' . $ordemLote,
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
        return 50;
    }
}
