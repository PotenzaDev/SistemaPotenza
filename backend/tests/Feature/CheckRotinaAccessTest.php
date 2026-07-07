<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RotinaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckRotinaAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RotinaSeeder::class);
    }

    public function test_funcionario_com_rotina_liberada_acessa_maquinas(): void
    {
        $funcionario = User::factory()->funcionario(['maquinas'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/maquinas')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_funcionario_sem_rotina_liberada_nao_acessa_maquinas(): void
    {
        $funcionario = User::factory()->funcionario(['dashboard'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/maquinas')
            ->assertForbidden();
    }

    public function test_funcionario_sem_rotinas_setadas_nao_acessa_maquinas(): void
    {
        $funcionario = User::factory()->funcionario()->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/maquinas')
            ->assertForbidden();
    }

    public function test_admin_acessa_rotina_independente_de_rotinas_liberadas(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/maquinas')
            ->assertOk();
    }

    public function test_funcionario_com_dashboard_liberado_acessa_dashboard(): void
    {
        $funcionario = User::factory()->funcionario(['dashboard'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk();
    }

    public function test_funcionario_sem_dashboard_liberado_nao_acessa_dashboard(): void
    {
        $funcionario = User::factory()->funcionario(['maquinas'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_funcionario_com_kanban_liberado_acessa_kanban(): void
    {
        $funcionario = User::factory()->funcionario(['kanban'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/kanban')
            ->assertOk();
    }

    public function test_funcionario_sem_kanban_liberado_nao_acessa_kanban(): void
    {
        $funcionario = User::factory()->funcionario(['dashboard'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/kanban')
            ->assertForbidden();
    }

    public function test_funcionario_com_operarios_liberado_lista_etapas_fluxo(): void
    {
        $funcionario = User::factory()->funcionario(['operarios'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/etapas-fluxo')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_funcionario_sem_operarios_liberado_nao_lista_etapas_fluxo(): void
    {
        $funcionario = User::factory()->funcionario(['dashboard'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/etapas-fluxo')
            ->assertForbidden();
    }

    public function test_funcionario_com_operarios_liberado_nao_pode_criar_etapa_fluxo(): void
    {
        $funcionario = User::factory()->funcionario(['operarios'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->postJson('/api/etapas-fluxo', ['nome' => 'Corte', 'ordem' => 1])
            ->assertForbidden();
    }
}
