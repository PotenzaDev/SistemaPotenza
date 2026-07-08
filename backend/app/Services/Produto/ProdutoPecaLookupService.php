<?php

declare(strict_types=1);

namespace App\Services\Produto;

use App\Models\Produto;
use App\Models\ProdutoPeca;

/**
 * Resolve o ProdutoPeca (cadastro local) a partir dos códigos do ERP
 * (cod_produto + cod_peca) vindos de um Apontamento. Usado para localizar
 * a ficha de setup (FichaCabecote) da peça sendo processada.
 */
class ProdutoPecaLookupService
{
    /**
     * Retorna null quando o produto ainda não foi importado localmente ou
     * quando o cod_peca não é numérico (mesma convenção de numeração usada
     * na importação — ver ProdutoImportTransform::numeroSemi).
     */
    public function resolver(string $codProduto, string $codPeca): ?ProdutoPeca
    {
        $produto = Produto::where('cod_produto', $codProduto)->first();

        if (! $produto) {
            return null;
        }

        $codPeca = trim($codPeca);

        if (! is_numeric($codPeca)) {
            return null;
        }

        return $produto->pecas()->where('numero', (int) (float) $codPeca)->first();
    }
}
