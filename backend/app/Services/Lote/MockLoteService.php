<?php

declare(strict_types=1);

namespace App\Services\Lote;

class MockLoteService implements LoteServiceInterface
{
    public function __construct(private int $fichasLoteCount = 1) {}

    /** Cria instância com contarFichasLote configurado — útil em tests com ->instance(). */
    public static function com(int $fichasLoteCount): self
    {
        return new self($fichasLoteCount);
    }

    public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array
    {
        return [
            'lote'        => ltrim($ordemLote, '0') ?: '0',
            'cod_produto' => 'PROD-TEST',
            'cod_peca'    => $codPeca,
            'desc_peca'   => 'Peça de Teste',
            'qtde_total'  => 300,
        ];
    }

    public function buscarFtecPecaPilha(string $codPeca): ?int
    {
        return 50;
    }

    public function contarFichasLote(string $ordemLote, string $codPeca): int
    {
        return $this->fichasLoteCount;
    }
}
