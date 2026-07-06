<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Produto\ProdutoImportTransform;
use Tests\TestCase;

class ProdutoImportTransformTest extends TestCase
{
    public function test_numero_semi_extrai_inteiro_de_codigo_numerico(): void
    {
        $this->assertSame(1, ProdutoImportTransform::numeroSemi('01', 5));
        $this->assertSame(23, ProdutoImportTransform::numeroSemi('23', 5));
        $this->assertSame(0, ProdutoImportTransform::numeroSemi('0', 5));
    }

    public function test_numero_semi_usa_fallback_quando_codigo_nao_numerico(): void
    {
        $this->assertSame(5, ProdutoImportTransform::numeroSemi('abc', 5));
        $this->assertSame(3, ProdutoImportTransform::numeroSemi('', 3));
        $this->assertSame(7, ProdutoImportTransform::numeroSemi(null, 7));
    }

    public function test_numero_semi_ignora_espacos_em_branco(): void
    {
        $this->assertSame(12, ProdutoImportTransform::numeroSemi('  12  ', 5));
    }

    public function test_dimensao_converte_comprimento_e_largura_de_metros_para_milimetros(): void
    {
        // Comprimento e largura vêm do ERP em metros; espessura já vem em mm.
        $this->assertSame('321 x 130 x 12 mm', ProdutoImportTransform::dimensao('.3210', '.1300', '12.000000'));
    }

    public function test_dimensao_mantem_casas_decimais_quando_necessario(): void
    {
        $this->assertSame('321.5 x 130 x 12.5 mm', ProdutoImportTransform::dimensao('.3215', '.13', '12.5'));
    }

    public function test_dimensao_ignora_valores_ausentes(): void
    {
        $this->assertSame('500 x 18 mm', ProdutoImportTransform::dimensao('.5', null, '18'));
        $this->assertSame('500 mm', ProdutoImportTransform::dimensao('.5', null, null));
    }

    public function test_dimensao_ignora_strings_vazias(): void
    {
        $this->assertSame('500 x 18 mm', ProdutoImportTransform::dimensao('.5', '', '18'));
    }

    public function test_dimensao_ignora_valores_nao_numericos(): void
    {
        $this->assertSame('500 mm', ProdutoImportTransform::dimensao('.5', 'abc', null));
    }

    public function test_dimensao_retorna_null_quando_todos_os_valores_ausentes(): void
    {
        $this->assertNull(ProdutoImportTransform::dimensao(null, null, null));
        $this->assertNull(ProdutoImportTransform::dimensao('', '', ''));
    }
}
