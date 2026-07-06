<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfiguracaoCabecoteMaquina;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaquinaCabecoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastrar_maquina_em_grupo_furadeira_salva_configuracao_de_cabecote(): void
    {
        $admin = User::factory()->admin()->create();
        $furadeira = EtapaFluxo::factory()->create(['requer_config_cabecote' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maquinas', [
                'etapa_fluxo_id' => $furadeira->id,
                'nome' => 'Furadeira Teste',
                'cabecotes_inferiores' => 2,
                'cabecotes_superiores' => 3,
                'cabecotes_topo' => 1,
                'cabecotes_traseiros' => 1,
                'pinos_por_cabecote' => 4,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.configuracao_cabecote.cabecotes_inferiores', 2)
            ->assertJsonPath('data.configuracao_cabecote.pinos_por_cabecote', 4);

        $maquinaId = $response->json('data.id');
        $this->assertDatabaseHas('configuracoes_cabecote_maquinas', [
            'maquina_id' => $maquinaId,
            'cabecotes_inferiores' => 2,
            'cabecotes_superiores' => 3,
        ]);
    }

    public function test_cadastrar_maquina_em_grupo_sem_flag_ignora_campos_de_cabecote(): void
    {
        $admin = User::factory()->admin()->create();
        $grupo = EtapaFluxo::factory()->create(['requer_config_cabecote' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maquinas', [
                'etapa_fluxo_id' => $grupo->id,
                'nome' => 'Serra Teste',
                'cabecotes_inferiores' => 5,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.configuracao_cabecote', null);

        $this->assertDatabaseMissing('configuracoes_cabecote_maquinas', [
            'maquina_id' => $response->json('data.id'),
        ]);
    }

    public function test_atualizar_grupo_para_um_que_nao_exige_cabecote_preserva_configuracao_existente(): void
    {
        $admin = User::factory()->admin()->create();
        $furadeira = EtapaFluxo::factory()->create(['requer_config_cabecote' => true]);
        $serra = EtapaFluxo::factory()->create(['requer_config_cabecote' => false]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $furadeira->id]);
        ConfiguracaoCabecoteMaquina::create([
            'maquina_id' => $maquina->id,
            'cabecotes_inferiores' => 2,
            'cabecotes_superiores' => 2,
            'cabecotes_topo' => 1,
            'cabecotes_traseiros' => 1,
            'pinos_por_cabecote' => 6,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maquinas/{$maquina->id}", [
                '_method' => 'PATCH',
                'etapa_fluxo_id' => $serra->id,
            ]);

        // a linha de configuração não é apagada — só deixa de ser exigida pelo
        // novo grupo (o frontend decide se mostra a aba com base no grupo, não
        // na presença destes dados)
        $response->assertOk()
            ->assertJsonPath('data.etapa_fluxo_id', $serra->id)
            ->assertJsonPath('data.configuracao_cabecote.cabecotes_inferiores', 2);

        $this->assertDatabaseHas('configuracoes_cabecote_maquinas', [
            'maquina_id' => $maquina->id,
            'cabecotes_inferiores' => 2,
        ]);
    }

    public function test_atualizar_campos_de_cabecote_de_maquina_furadeira_sobrescreve_valores(): void
    {
        $admin = User::factory()->admin()->create();
        $furadeira = EtapaFluxo::factory()->create(['requer_config_cabecote' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $furadeira->id]);
        ConfiguracaoCabecoteMaquina::create([
            'maquina_id' => $maquina->id,
            'cabecotes_inferiores' => 2,
            'cabecotes_superiores' => 2,
            'cabecotes_topo' => 1,
            'cabecotes_traseiros' => 1,
            'pinos_por_cabecote' => 6,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maquinas/{$maquina->id}", [
                '_method' => 'PATCH',
                'cabecotes_inferiores' => 9,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.configuracao_cabecote.cabecotes_inferiores', 9);

        $this->assertDatabaseHas('configuracoes_cabecote_maquinas', [
            'maquina_id' => $maquina->id,
            'cabecotes_inferiores' => 9,
        ]);
    }
}
