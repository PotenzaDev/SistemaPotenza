<?php

declare(strict_types=1);

namespace App\Services\Produto;

use App\Exceptions\BusinessException;
use App\Models\Produto;
use App\Models\ProdutoPeca;
use Illuminate\Support\Collection;

class ProdutoService
{
    public function listar(): Collection
    {
        return Produto::withCount('pecas')->orderBy('nome')->get();
    }

    public function buscarComPecas(int $id): ?Produto
    {
        return Produto::with(['pecas' => function ($query) {
            $query->withCount('fichasCabecote')->with('ultimaFichaCabecote');
        }])->find($id);
    }

    public function encontrar(int $id): ?Produto
    {
        return Produto::find($id);
    }

    public function desativar(Produto $produto): Produto
    {
        $produto->update(['ativo' => false]);

        return $produto;
    }

    /**
     * Localiza peças pelo prefixo do código de barras escaneado. Só os 5
     * primeiros dígitos identificam o formato/peça; os 2 seguintes são a cor
     * (CodiSemiAcabado do ERP, 7 chars: 5+2 — ver frontend/src/lib/barcode.ts).
     * Ignoramos a cor para achar qualquer variante do mesmo formato, mesmo em
     * outro produto.
     */
    public function buscarPorCodigoBarra(string $codigo): Collection
    {
        $digitos = substr((string) preg_replace('/\D/', '', $codigo), 0, 7);

        if (strlen($digitos) < 5) {
            throw new BusinessException('Informe ao menos 5 dígitos do código.', 422);
        }

        $prefixo = intdiv((int) $digitos, 100);

        return ProdutoPeca::with(['produto', 'ultimaFichaCabecote'])
            ->whereBetween('numero', [$prefixo * 100, $prefixo * 100 + 99])
            ->orderBy('ordem')
            ->get();
    }
}
