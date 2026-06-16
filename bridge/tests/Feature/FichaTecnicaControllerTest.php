<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class FichaTecnicaControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['bridge.token' => 'test-token']);
    }

    public function test_retorna_401_sem_token(): void
    {
        $response = $this->getJson('/api/ficha-tecnica/lote?lote=123&cod_peca=ABC');

        $response->assertStatus(401);
    }

    public function test_retorna_401_com_token_invalido(): void
    {
        $response = $this->withHeader('X-Bridge-Token', 'token-errado')
            ->getJson('/api/ficha-tecnica/lote?lote=123&cod_peca=ABC');

        $response->assertStatus(401);
    }

    public function test_retorna_dados_da_ficha_tecnica_do_lote(): void
    {
        $response = $this->withHeader('X-Bridge-Token', 'test-token')
            ->getJson('/api/ficha-tecnica/lote?lote=123&cod_peca=ABC');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'lote',
                'cod_produto',
                'cod_peca',
                'desc_peca',
                'qtde_total',
            ]);
    }

    public function test_retorna_404_quando_ficha_tecnica_nao_encontrada(): void
    {
        $response = $this->withHeader('X-Bridge-Token', 'test-token')
            ->getJson('/api/ficha-tecnica/lote?lote=123&cod_peca=0000000');

        $response->assertStatus(404);
    }

    public function test_retorna_ftec_peca_pilha(): void
    {
        $response = $this->withHeader('X-Bridge-Token', 'test-token')
            ->getJson('/api/ficha-tecnica/pilha?cod_peca=ABC');

        $response->assertStatus(200)
            ->assertJson(['ftec_peca_pilha' => 50]);
    }

    public function test_retorna_ftec_peca_pilha_nulo_quando_nao_encontrado(): void
    {
        $response = $this->withHeader('X-Bridge-Token', 'test-token')
            ->getJson('/api/ficha-tecnica/pilha?cod_peca=0000000');

        $response->assertStatus(200)
            ->assertJson(['ftec_peca_pilha' => null]);
    }
}
