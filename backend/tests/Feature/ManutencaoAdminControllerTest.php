<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Maquina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManutencaoAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_criar_os_pelo_painel_administrativo(): void
    {
        $admin = User::factory()->admin()->create();
        $maquina = Maquina::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manutencao/admin', [
                'maquina_id' => $maquina->id,
                'solicitante' => 'Gustavo (painel)',
                'motivo' => 'Ruído incomum no eixo principal',
                'prioridade' => 'alta',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'aberta')
            ->assertJsonPath('data.prioridade', 'alta')
            ->assertJsonPath('data.solicitante', 'Gustavo (painel)')
            ->assertJsonPath('data.maquina.id', $maquina->id);

        $this->assertDatabaseHas('ordens_manutencao', [
            'maquina_id' => $maquina->id,
            'solicitante' => 'Gustavo (painel)',
            'status' => 'aberta',
        ]);
    }

    public function test_gestor_pode_criar_os_pelo_painel_administrativo(): void
    {
        $gestor = User::factory()->gestor()->create();
        $maquina = Maquina::factory()->create();

        $response = $this->actingAs($gestor, 'sanctum')
            ->postJson('/api/manutencao/admin', [
                'maquina_id' => $maquina->id,
                'solicitante' => 'Gestor Teste',
                'motivo' => 'Vazamento de óleo',
                'prioridade' => 'critica',
            ]);

        $response->assertCreated();
    }

    public function test_operario_nao_pode_criar_os_pelo_painel_administrativo(): void
    {
        $operario = User::factory()->operario()->create();
        $maquina = Maquina::factory()->create();

        $response = $this->actingAs($operario, 'sanctum')
            ->postJson('/api/manutencao/admin', [
                'maquina_id' => $maquina->id,
                'solicitante' => 'Operário Teste',
                'motivo' => 'Motivo qualquer',
                'prioridade' => 'baixa',
            ]);

        $response->assertForbidden();
    }

    public function test_criar_os_sem_maquina_falha_validacao(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manutencao/admin', [
                'solicitante' => 'Sem máquina',
                'motivo' => 'Motivo qualquer',
                'prioridade' => 'normal',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['maquina_id']);
    }

    public function test_criar_os_com_prioridade_invalida_falha_validacao(): void
    {
        $admin = User::factory()->admin()->create();
        $maquina = Maquina::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manutencao/admin', [
                'maquina_id' => $maquina->id,
                'solicitante' => 'Teste',
                'motivo' => 'Motivo qualquer',
                'prioridade' => 'urgente',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['prioridade']);
    }
}
