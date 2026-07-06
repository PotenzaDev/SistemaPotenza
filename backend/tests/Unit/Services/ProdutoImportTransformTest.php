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

    public function test_dimensao_monta_string_com_todos_os_valores(): void
    {
        $this->assertSame('500x300x18mm', ProdutoImportTransform::dimensao('500', '300', '18'));
    }

    public function test_dimensao_ignora_valores_ausentes(): void
    {
        $this->assertSame('500x18mm', ProdutoImportTransform::dimensao('500', null, '18'));
        $this->assertSame('500mm', ProdutoImportTransform::dimensao('500', null, null));
    }

    public function test_dimensao_ignora_strings_vazias(): void
    {
        $this->assertSame('500x18mm', ProdutoImportTransform::dimensao('500', '', '18'));
    }

    public function test_dimensao_retorna_null_quando_todos_os_valores_ausentes(): void
    {
        $this->assertNull(ProdutoImportTransform::dimensao(null, null, null));
        $this->assertNull(ProdutoImportTransform::dimensao('', '', ''));
    }
}
