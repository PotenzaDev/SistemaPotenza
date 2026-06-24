<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Rotina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RotinaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_listar_rotinas_de_topo_com_filhos(): void
    {
        $admin = User::factory()->admin()->create();
        $pai = Rotina::factory()->create(['nome' => 'Cadastro', 'slug' => 'cadastro', 'parent_id' => null]);
        Rotina::factory()->create(['nome' => 'Máquinas', 'slug' => 'maquinas_filho', 'parent_id' => $pai->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/rotinas')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertCount(1, $response->json('data.0.filhos'));
    }

    public function test_admin_pode_cadastrar_rotina_pai(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/rotinas', [
                'nome'   => 'Cadastro',
                'slug'   => 'cadastro',
                'pagina' => '/cadastro',
                'icone'  => 'Users',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'cadastro');

        $this->assertDatabaseHas('rotinas', ['slug' => 'cadastro', 'parent_id' => null]);
    }

    public function test_admin_pode_cadastrar_sub_rotina_de_uma_rotina_pai(): void
    {
        $admin = User::factory()->admin()->create();
        $pai = Rotina::factory()->create(['parent_id' => null]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/rotinas', [
                'nome'      => 'Máquinas',
                'slug'      => 'maquinas_sub',
                'pagina'    => '/cadastro/maquinas',
                'icone'     => 'Cpu',
                'parent_id' => $pai->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $pai->id);
    }

    public function test_admin_pode_cadastrar_rotina_pai_sem_pagina(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/rotinas', [
                'nome'  => 'Cadastro',
                'slug'  => 'cadastro_grupo',
                'icone' => 'Boxes',
            ])
            ->assertCreated()
            ->assertJsonPath('data.pagina', null);

        $this->assertDatabaseHas('rotinas', ['slug' => 'cadastro_grupo', 'pagina' => null]);
    }

    public function test_cadastro_rejeita_sub_rotina_sem_pagina(): void
    {
        $admin = User::factory()->admin()->create();
        $pai = Rotina::factory()->create(['parent_id' => null]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/rotinas', [
                'nome'      => 'Sem Página',
                'slug'      => 'sem_pagina',
                'icone'     => 'Users',
                'parent_id' => $pai->id,
            ])
            ->assertStatus(422);
    }

    public function test_cadastro_rejeita_sub_rotina_como_pai_de_outra_sub_rotina(): void
    {
        $admin = User::factory()->admin()->create();
        $pai = Rotina::factory()->create(['parent_id' => null]);
        $filho = Rotina::factory()->create(['parent_id' => $pai->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/rotinas', [
                'nome'      => 'Neto',
                'slug'      => 'neto',
                'pagina'    => '/cadastro/neto',
                'icone'     => 'Users',
                'parent_id' => $filho->id,
            ])
            ->assertStatus(422);
    }

    public function test_cadastro_rejeita_slug_em_formato_invalido(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/rotinas', [
                'nome'   => 'Inválido',
                'slug'   => 'Slug Inválido',
                'pagina' => '/inválido',
                'icone'  => 'Users',
            ])
            ->assertStatus(422);
    }

    public function test_cadastro_rejeita_icone_em_formato_invalido(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/rotinas', [
                'nome'   => 'Inválido',
                'slug'   => 'icone_invalido',
                'pagina' => '/icone-invalido',
                'icone'  => 'fas fa-user-alt',
            ])
            ->assertStatus(422);
    }

    public function test_cadastro_rejeita_pagina_sem_barra_inicial(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/rotinas', [
                'nome'   => 'Inválido',
                'slug'   => 'pagina_invalida',
                'pagina' => 'sem-barra',
                'icone'  => 'Users',
            ])
            ->assertStatus(422);
    }

    public function test_admin_pode_atualizar_rotina(): void
    {
        $admin = User::factory()->admin()->create();
        $rotina = Rotina::factory()->create(['nome' => 'Antigo']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/rotinas/{$rotina->id}", ['nome' => 'Novo'])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Novo');
    }

    public function test_atualizacao_rejeita_rotina_como_pai_de_si_mesma(): void
    {
        $admin = User::factory()->admin()->create();
        $rotina = Rotina::factory()->create(['parent_id' => null]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/rotinas/{$rotina->id}", ['parent_id' => $rotina->id])
            ->assertStatus(422);
    }

    public function test_admin_pode_remover_rotina(): void
    {
        $admin = User::factory()->admin()->create();
        $rotina = Rotina::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/rotinas/{$rotina->id}")
            ->assertOk();

        $this->assertDatabaseMissing('rotinas', ['id' => $rotina->id]);
    }

    public function test_gestor_nao_pode_acessar_rotinas(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/rotinas')
            ->assertForbidden();
    }

    public function test_funcionario_nao_pode_acessar_rotinas(): void
    {
        $funcionario = User::factory()->funcionario()->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/rotinas')
            ->assertForbidden();
    }

    public function test_admin_gestor_e_funcionario_podem_acessar_menu(): void
    {
        $pai = Rotina::factory()->create(['parent_id' => null, 'ativo' => true]);
        Rotina::factory()->create(['parent_id' => $pai->id, 'ativo' => true]);
        Rotina::factory()->create(['parent_id' => $pai->id, 'ativo' => false]);

        foreach (['admin', 'gestor', 'funcionario'] as $factoryState) {
            $user = User::factory()->{$factoryState}()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/menu')
                ->assertOk()
                ->assertJsonPath('success', true);

            $this->assertCount(1, $response->json('data'));
            $this->assertCount(1, $response->json('data.0.filhos'));
        }
    }

    public function test_operario_nao_pode_acessar_menu(): void
    {
        $operario = User::factory()->operario()->create();

        $this->actingAs($operario, 'sanctum')
            ->getJson('/api/menu')
            ->assertForbidden();
    }

    public function test_menu_oculta_rotinas_inativas_de_topo(): void
    {
        $admin = User::factory()->admin()->create();
        Rotina::factory()->create(['parent_id' => null, 'ativo' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/menu')
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }
}
