<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MotivoPausa;
use App\Models\Rotina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotivoPausaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_cadastrar_motivo(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/motivos-pausa', ['nome' => 'Manutenção']);

        $response->assertCreated()
            ->assertJsonPath('data.nome', 'Manutenção')
            ->assertJsonPath('data.ativo', true)
            ->assertJsonPath('data.is_sistema', false);

        $this->assertDatabaseHas('motivos_pausa', ['nome' => 'Manutenção', 'is_sistema' => false]);
    }

    public function test_funcionario_com_rotina_liberada_pode_listar_motivos(): void
    {
        Rotina::factory()->create(['slug' => 'motivos_pausa']);
        $funcionario = User::factory()->funcionario(['motivos_pausa'])->create();
        MotivoPausa::factory()->create(['nome' => 'Motivo Teste A']);
        MotivoPausa::factory()->create(['nome' => 'Motivo Teste B']);

        $response = $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/motivos-pausa')
            ->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome');
        $this->assertContains('Motivo Teste A', $nomes);
        $this->assertContains('Motivo Teste B', $nomes);
    }

    public function test_nome_duplicado_e_rejeitado(): void
    {
        $admin = User::factory()->admin()->create();
        MotivoPausa::factory()->create(['nome' => 'Manutenção']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/motivos-pausa', ['nome' => 'Manutenção'])
            ->assertStatus(422);
    }

    public function test_admin_pode_atualizar_motivo(): void
    {
        $admin = User::factory()->admin()->create();
        $motivo = MotivoPausa::factory()->create(['nome' => 'Original']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/motivos-pausa/{$motivo->id}", ['nome' => 'Atualizado'])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Atualizado');
    }

    public function test_destroy_desativa_ao_inves_de_apagar(): void
    {
        $admin = User::factory()->admin()->create();
        $motivo = MotivoPausa::factory()->create(['ativo' => true]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/motivos-pausa/{$motivo->id}")
            ->assertOk()
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('motivos_pausa', ['id' => $motivo->id, 'ativo' => false]);
    }

    public function test_motivo_de_sistema_nao_pode_ser_atualizado(): void
    {
        $admin = User::factory()->admin()->create();
        $motivo = MotivoPausa::factory()->create(['is_sistema' => true]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/motivos-pausa/{$motivo->id}", ['nome' => 'Tentativa'])
            ->assertStatus(403);
    }

    public function test_motivo_de_sistema_nao_pode_ser_removido(): void
    {
        $admin = User::factory()->admin()->create();
        $motivo = MotivoPausa::factory()->create(['is_sistema' => true]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/motivos-pausa/{$motivo->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('motivos_pausa', ['id' => $motivo->id, 'ativo' => true]);
    }

    public function test_operario_lista_apenas_motivos_ativos_e_nao_sistema(): void
    {
        $operario = User::factory()->operario()->create();
        MotivoPausa::factory()->create(['nome' => 'Selecionavel', 'ativo' => true, 'is_sistema' => false]);
        MotivoPausa::factory()->create(['nome' => 'Inativo', 'ativo' => false, 'is_sistema' => false]);
        MotivoPausa::factory()->create(['nome' => 'Sistema', 'ativo' => true, 'is_sistema' => true]);

        $response = $this->actingAs($operario, 'sanctum')
            ->getJson('/api/motivos-pausa/disponiveis')
            ->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Selecionavel');
    }
}
