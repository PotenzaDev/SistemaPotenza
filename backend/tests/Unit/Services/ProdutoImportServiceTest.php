<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Services\Produto\ProdutoImportService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProdutoImportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bridge.url' => 'http://bridge.test/api',
            'services.bridge.token' => 'test-token',
        ]);
    }

    public function test_buscar_no_erp_monta_url_e_retorna_dados_da_bridge(): void
    {
        Http::fake([
            'bridge.test/api/produtos*' => Http::response([
                ['cod_produto' => '123', 'nome' => 'Cadeira', 'grupo' => 'MOVEIS', 'sub_grupo' => 'CADEIRAS'],
            ], 200),
        ]);

        $produtos = (new ProdutoImportService)->buscarNoErp('FBM', 'Cadeira', null);

        $this->assertSame('123', $produtos[0]['cod_produto']);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'http://bridge.test/api/produtos?')
                && $request['empresa'] === 'FBM'
                && $request['nome'] === 'Cadeira'
                && array_key_exists('data_corte', $request->data())
                && $request->header('X-Bridge-Token') === ['test-token'];
        });
    }

    public function test_buscar_no_erp_usa_janela_relativa_de_12_meses(): void
    {
        Http::fake([
            'bridge.test/api/produtos*' => Http::response([], 200),
        ]);

        (new ProdutoImportService)->buscarNoErp('FBM', 'Cadeira', null);

        $dataCorteEsperada = now()->subMonths(12)->toDateString();

        Http::assertSent(function ($request) use ($dataCorteEsperada) {
            return $request['data_corte'] === $dataCorteEsperada;
        });
    }

    public function test_buscar_no_erp_lanca_exception_503_quando_bridge_retorna_erro(): void
    {
        Http::fake([
            'bridge.test/api/produtos*' => Http::response(['message' => 'Erro interno.'], 500),
        ]);

        try {
            (new ProdutoImportService)->buscarNoErp('FBM', 'Cadeira', null);
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }

    public function test_buscar_no_erp_lanca_exception_503_quando_bridge_retorna_401(): void
    {
        Http::fake([
            'bridge.test/api/produtos*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        try {
            (new ProdutoImportService)->buscarNoErp('FBM', 'Cadeira', null);
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertStringContainsString('BRIDGE_API_TOKEN', $e->getMessage());
        }
    }

    public function test_buscar_no_erp_lanca_exception_503_quando_bridge_indisponivel(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        try {
            (new ProdutoImportService)->buscarNoErp('FBM', 'Cadeira', null);
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }

    public function test_buscar_no_erp_lanca_exception_503_quando_url_nao_configurada(): void
    {
        config(['services.bridge.url' => '']);

        try {
            (new ProdutoImportService)->buscarNoErp('FBM', 'Cadeira', null);
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertStringContainsString('BRIDGE_API_URL', $e->getMessage());
        }
    }

    public function test_buscar_sub_grupos_no_erp_monta_url_e_retorna_dados_da_bridge(): void
    {
        Http::fake([
            'bridge.test/api/produtos/sub-grupos*' => Http::response(['SUBGRUPO_A', 'SUBGRUPO_B'], 200),
        ]);

        $subGrupos = (new ProdutoImportService)->buscarSubGruposNoErp('FBM');

        $this->assertSame(['SUBGRUPO_A', 'SUBGRUPO_B'], $subGrupos);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'http://bridge.test/api/produtos/sub-grupos?')
                && $request['empresa'] === 'FBM';
        });
    }

    public function test_buscar_sub_grupos_no_erp_lanca_exception_503_quando_bridge_retorna_erro(): void
    {
        Http::fake([
            'bridge.test/api/produtos/sub-grupos*' => Http::response(['message' => 'Erro interno.'], 500),
        ]);

        try {
            (new ProdutoImportService)->buscarSubGruposNoErp('FBM');
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }

    public function test_importar_lanca_exception_503_quando_bridge_retorna_erro(): void
    {
        Http::fake([
            'bridge.test/api/produtos/123/pecas' => Http::response(['message' => 'Erro interno.'], 500),
        ]);

        try {
            (new ProdutoImportService)->importar([
                'cod_produto' => '123',
                'nome' => 'Cadeira',
                'grupo' => 'MOVEIS',
                'sub_grupo' => 'CADEIRAS',
                'empresa' => 'FBM',
            ]);
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }

    public function test_importar_lanca_exception_503_quando_bridge_retorna_401(): void
    {
        Http::fake([
            'bridge.test/api/produtos/123/pecas' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        try {
            (new ProdutoImportService)->importar([
                'cod_produto' => '123',
                'nome' => 'Cadeira',
                'grupo' => 'MOVEIS',
                'sub_grupo' => 'CADEIRAS',
                'empresa' => 'FBM',
            ]);
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertStringContainsString('BRIDGE_API_TOKEN', $e->getMessage());
        }
    }

    public function test_importar_lanca_exception_503_quando_bridge_indisponivel(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        try {
            (new ProdutoImportService)->importar([
                'cod_produto' => '123',
                'nome' => 'Cadeira',
                'grupo' => 'MOVEIS',
                'sub_grupo' => 'CADEIRAS',
                'empresa' => 'FBM',
            ]);
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }

    public function test_importar_lanca_exception_503_quando_url_nao_configurada(): void
    {
        config(['services.bridge.url' => '']);

        try {
            (new ProdutoImportService)->importar([
                'cod_produto' => '123',
                'nome' => 'Cadeira',
                'grupo' => 'MOVEIS',
                'sub_grupo' => 'CADEIRAS',
                'empresa' => 'FBM',
            ]);
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertStringContainsString('BRIDGE_API_URL', $e->getMessage());
        }
    }
}
