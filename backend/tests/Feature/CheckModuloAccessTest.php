<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckModuloAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_funcionario_com_modulo_liberado_acessa_maquinas(): void
    {
        $funcionario = User::factory()->funcionario(['maquinas'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/maquinas')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_funcionario_sem_modulo_liberado_nao_acessa_maquinas(): void
    {
        $funcionario = User::factory()->funcionario(['dashboard'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/maquinas')
            ->assertForbidden();
    }

    public function test_funcionario_sem_modulos_setados_nao_acessa_maquinas(): void
    {
        $funcionario = User::factory()->funcionario()->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/maquinas')
            ->assertForbidden();
    }

    public function test_admin_acessa_modulo_independente_de_modulos_permitidos(): void
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
}
