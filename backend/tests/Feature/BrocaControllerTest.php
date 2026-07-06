<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Broca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrocaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_cadastrar_broca(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/brocas', [
                'codigo' => 'BR-0001',
                'espessura_mm' => 8.5,
                'rotacao' => 'direita',
                'altura_mm' => 45,
                'furo_passante' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.codigo', 'BR-0001')
            ->assertJsonPath('data.rotacao', 'direita');

        $this->assertDatabaseHas('brocas', ['codigo' => 'BR-0001']);
    }

    public function test_funcionario_com_rotina_liberada_pode_listar_brocas(): void
    {
        $funcionario = User::factory()->funcionario(['brocas'])->create();
        Broca::factory()->count(2)->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/brocas')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_codigo_duplicado_e_rejeitado(): void
    {
        $admin = User::factory()->admin()->create();
        Broca::factory()->create(['codigo' => 'BR-0001']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/brocas', [
                'codigo' => 'BR-0001',
                'espessura_mm' => 5,
                'rotacao' => 'esquerda',
                'altura_mm' => 30,
                'furo_passante' => false,
            ])
            ->assertStatus(422);
    }

    public function test_rotacao_invalida_e_rejeitada(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/brocas', [
                'codigo' => 'BR-0002',
                'espessura_mm' => 5,
                'rotacao' => 'centro',
                'altura_mm' => 30,
                'furo_passante' => false,
            ])
            ->assertStatus(422);
    }

    public function test_admin_pode_atualizar_broca(): void
    {
        $admin = User::factory()->admin()->create();
        $broca = Broca::factory()->create(['espessura_mm' => 5]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/brocas/{$broca->id}", ['espessura_mm' => 9.5])
            ->assertOk()
            ->assertJsonPath('data.espessura_mm', 9.5);
    }

    public function test_destroy_desativa_ao_inves_de_apagar(): void
    {
        $admin = User::factory()->admin()->create();
        $broca = Broca::factory()->create(['ativo' => true]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/brocas/{$broca->id}")
            ->assertOk()
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('brocas', ['id' => $broca->id, 'ativo' => false]);
    }
}
