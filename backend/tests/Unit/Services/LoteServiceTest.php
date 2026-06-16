<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Services\Lote\LoteService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoteServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bridge.url'   => 'http://bridge.test/api',
            'services.bridge.token' => 'test-token',
        ]);
    }

    public function test_busca_por_ordem_lote_retorna_dados_da_bridge(): void
    {
        Http::fake([
            'bridge.test/api/ficha-tecnica/lote*' => Http::response([
                'lote'        => '6854',
                'cod_produto' => 'PROD-6854',
                'cod_peca'    => 'ABC',
                'desc_peca'   => 'Peça Teste',
                'qtde_total'  => 300,
            ], 200),
        ]);

        $dados = (new LoteService())->buscarPorOrdemLote('06854', 'ABC');

        $this->assertSame('6854', $dados['lote']);
        $this->assertSame(300, $dados['qtde_total']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://bridge.test/api/ficha-tecnica/lote?lote=6854&cod_peca=ABC'
                && $request->header('X-Bridge-Token') === ['test-token'];
        });
    }

    public function test_busca_por_ordem_lote_lanca_exception_422_quando_nao_encontrado(): void
    {
        Http::fake([
            'bridge.test/api/ficha-tecnica/lote*' => Http::response(['message' => 'Não encontrado.'], 404),
        ]);

        try {
            (new LoteService())->buscarPorOrdemLote('06854', 'ABC');
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_busca_por_ordem_lote_lanca_exception_503_quando_bridge_retorna_erro(): void
    {
        Http::fake([
            'bridge.test/api/ficha-tecnica/lote*' => Http::response(['message' => 'Erro interno.'], 500),
        ]);

        try {
            (new LoteService())->buscarPorOrdemLote('06854', 'ABC');
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }

    public function test_busca_por_ordem_lote_lanca_exception_503_quando_bridge_indisponivel(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        try {
            (new LoteService())->buscarPorOrdemLote('06854', 'ABC');
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }

    public function test_busca_ftec_peca_pilha_retorna_valor_da_bridge(): void
    {
        Http::fake([
            'bridge.test/api/ficha-tecnica/pilha*' => Http::response(['ftec_peca_pilha' => 50], 200),
        ]);

        $valor = (new LoteService())->buscarFtecPecaPilha('ABC');

        $this->assertSame(50, $valor);
    }

    public function test_busca_ftec_peca_pilha_retorna_null_quando_bridge_retorna_null(): void
    {
        Http::fake([
            'bridge.test/api/ficha-tecnica/pilha*' => Http::response(['ftec_peca_pilha' => null], 200),
        ]);

        $valor = (new LoteService())->buscarFtecPecaPilha('ABC');

        $this->assertNull($valor);
    }

    public function test_busca_ftec_peca_pilha_lanca_exception_503_quando_bridge_retorna_erro(): void
    {
        Http::fake([
            'bridge.test/api/ficha-tecnica/pilha*' => Http::response(['message' => 'Erro interno.'], 500),
        ]);

        try {
            (new LoteService())->buscarFtecPecaPilha('ABC');
            $this->fail('Esperava BusinessException.');
        } catch (BusinessException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }
}
