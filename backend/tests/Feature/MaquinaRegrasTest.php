<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\RegraMaquina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaquinaRegrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastrar_maquina_salva_regras_customizadas(): void
    {
        $admin = User::factory()->admin()->create();
        $grupo = EtapaFluxo::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maquinas', [
                'etapa_fluxo_id' => $grupo->id,
                'nome' => 'Máquina Regras Teste',
                'possui_setup' => false,
                'possui_producao' => true,
                'permite_multiplas_passagens' => false,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.regra_maquina.possui_setup', false)
            ->assertJsonPath('data.regra_maquina.possui_producao', true)
            ->assertJsonPath('data.regra_maquina.permite_multiplas_passagens', false)
            ->assertJsonPath('data.regra_maquina.limite_passagens', null);

        $this->assertDatabaseHas('regras_maquinas', [
            'maquina_id' => $response->json('data.id'),
            'possui_setup' => false,
            'possui_producao' => true,
            'permite_multiplas_passagens' => false,
        ]);
    }

    public function test_cadastrar_maquina_sem_enviar_regras_aplica_defaults(): void
    {
        $admin = User::factory()->admin()->create();
        $grupo = EtapaFluxo::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maquinas', [
                'etapa_fluxo_id' => $grupo->id,
                'nome' => 'Máquina Sem Regras Explícitas',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.regra_maquina.possui_setup', true)
            ->assertJsonPath('data.regra_maquina.possui_producao', true)
            ->assertJsonPath('data.regra_maquina.permite_multiplas_passagens', true)
            ->assertJsonPath('data.regra_maquina.limite_passagens', null);
    }

    public function test_atualizar_limite_de_passagens_preserva_demais_regras(): void
    {
        $admin = User::factory()->admin()->create();
        $maquina = Maquina::factory()->create();
        RegraMaquina::create([
            'maquina_id' => $maquina->id,
            'possui_setup' => false,
            'possui_producao' => true,
            'permite_multiplas_passagens' => true,
            'limite_passagens' => null,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maquinas/{$maquina->id}", [
                '_method' => 'PATCH',
                'limite_passagens' => 3,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.regra_maquina.possui_setup', false)
            ->assertJsonPath('data.regra_maquina.limite_passagens', 3);

        $this->assertDatabaseHas('regras_maquinas', [
            'maquina_id' => $maquina->id,
            'possui_setup' => false,
            'limite_passagens' => 3,
        ]);
    }

    public function test_validacao_rejeita_limite_de_passagens_menor_que_dois(): void
    {
        $admin = User::factory()->admin()->create();
        $grupo = EtapaFluxo::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maquinas', [
                'etapa_fluxo_id' => $grupo->id,
                'nome' => 'Máquina Limite Inválido',
                'permite_multiplas_passagens' => true,
                'limite_passagens' => 1,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('limite_passagens');
    }
}
