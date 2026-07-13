<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EtapaFluxo;
use App\Models\Operario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperarioControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_cadastrar_operario(): void
    {
        $admin = User::factory()->admin()->create();
        $etapa = EtapaFluxo::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/operarios', [
                'name' => 'João Silva',
                'email' => 'joao@example.com',
                'password' => 'senha123',
                'etapa_fluxo_id' => $etapa->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.name', 'João Silva')
            ->assertJsonPath('data.user.email', 'joao@example.com')
            ->assertJsonPath('data.etapa_fluxo.id', $etapa->id)
            ->assertJsonMissingPath('data.user.password');

        $this->assertDatabaseHas('users', ['email' => 'joao@example.com', 'role' => 'operario']);
        $this->assertDatabaseHas('operarios', ['etapa_fluxo_id' => $etapa->id]);
    }

    public function test_email_duplicado_e_rejeitado(): void
    {
        $admin = User::factory()->admin()->create();
        $etapa = EtapaFluxo::factory()->create();
        $existente = Operario::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/operarios', [
                'name' => 'Outro',
                'email' => $existente->user->email,
                'password' => 'senha123',
                'etapa_fluxo_id' => $etapa->id,
            ])
            ->assertStatus(422);
    }

    public function test_admin_pode_listar_operarios(): void
    {
        $admin = User::factory()->admin()->create();
        Operario::factory()->count(2)->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/operarios')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_pode_ver_operario(): void
    {
        $admin = User::factory()->admin()->create();
        $operario = Operario::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/operarios/{$operario->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $operario->id)
            ->assertJsonPath('data.user.id', $operario->user_id);
    }

    public function test_admin_pode_atualizar_nome_e_etapa(): void
    {
        $admin = User::factory()->admin()->create();
        $operario = Operario::factory()->create();
        $novaEtapa = EtapaFluxo::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/operarios/{$operario->id}", [
                'name' => 'Nome Atualizado',
                'etapa_fluxo_id' => $novaEtapa->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Nome Atualizado')
            ->assertJsonPath('data.etapa_fluxo.id', $novaEtapa->id);

        $this->assertDatabaseHas('users', ['id' => $operario->user_id, 'name' => 'Nome Atualizado']);
    }

    public function test_admin_pode_desativar_conta_do_operario_via_update(): void
    {
        $admin = User::factory()->admin()->create();
        $operario = Operario::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/operarios/{$operario->id}", ['ativo' => false])
            ->assertOk()
            ->assertJsonPath('data.user.ativo', false);
    }

    public function test_destroy_remove_operario_e_usuario(): void
    {
        $admin = User::factory()->admin()->create();
        $operario = Operario::factory()->create();
        $userId = $operario->user_id;

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/operarios/{$operario->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseMissing('operarios', ['id' => $operario->id]);
    }
}
