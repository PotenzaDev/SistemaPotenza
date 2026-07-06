<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ProdutoControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['bridge.token' => 'test-token']);
    }

    public function test_listar_produtos_retorna_401_sem_token(): void
    {
        $response = $this->getJson('/api/produtos?empresa=FBM&nome=CADEIRA&data_corte=2026-01-01');

        $response->assertStatus(401);
    }

    public function test_listar_produtos_retorna_401_com_token_invalido(): void
    {
        $response = $this->withHeader('X-Bridge-Token', 'token-errado')
            ->getJson('/api/produtos?empresa=FBM&nome=CADEIRA&data_corte=2026-01-01');

        $response->assertStatus(401);
    }

    public function test_sub_grupos_retorna_401_sem_token(): void
    {
        $response = $this->getJson('/api/produtos/sub-grupos?empresa=FBM&data_corte=2026-01-01');

        $response->assertStatus(401);
    }

    public function test_sub_grupos_retorna_401_com_token_invalido(): void
    {
        $response = $this->withHeader('X-Bridge-Token', 'token-errado')
            ->getJson('/api/produtos/sub-grupos?empresa=FBM&data_corte=2026-01-01');

        $response->assertStatus(401);
    }

    public function test_pecas_retorna_401_sem_token(): void
    {
        $response = $this->getJson('/api/produtos/ABC/pecas');

        $response->assertStatus(401);
    }

    public function test_pecas_retorna_401_com_token_invalido(): void
    {
        $response = $this->withHeader('X-Bridge-Token', 'token-errado')
            ->getJson('/api/produtos/ABC/pecas');

        $response->assertStatus(401);
    }
}
