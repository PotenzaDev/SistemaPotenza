<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChamadaSuporte;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChamadaSuporteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_operario_com_sessao_ativa_pode_chamar_suporte(): void
    {
        $operario = Operario::factory()->create();
        $sessao   = SessaoTrabalho::factory()->create(['operario_id' => $operario->id]);

        $response = $this->actingAs($operario->user, 'sanctum')
            ->postJson('/api/apontamento/chamar-suporte')
            ->assertCreated();

        $this->assertDatabaseHas('chamadas_suporte', [
            'sessao_trabalho_id' => $sessao->id,
            'maquina_id'         => $sessao->maquina_id,
            'operario_id'        => $operario->id,
            'origem'             => 'operario',
        ]);
    }

    public function test_operario_sem_sessao_ativa_nao_pode_chamar_suporte(): void
    {
        $operario = Operario::factory()->create();

        $this->actingAs($operario->user, 'sanctum')
            ->postJson('/api/apontamento/chamar-suporte')
            ->assertStatus(422);

        $this->assertDatabaseCount('chamadas_suporte', 0);
    }

    public function test_manutencao_pode_chamar_suporte(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manutencao/admin/chamar-suporte')
            ->assertCreated();

        $this->assertDatabaseHas('chamadas_suporte', ['origem' => 'manutencao']);
    }

    public function test_admin_lista_apenas_chamadas_nao_visualizadas(): void
    {
        $admin    = User::factory()->admin()->create();
        $operario = Operario::factory()->create();

        $naoVisualizada = ChamadaSuporte::create([
            'operario_id' => $operario->id,
            'origem'      => 'operario',
        ]);
        ChamadaSuporte::create([
            'operario_id'    => $operario->id,
            'origem'         => 'operario',
            'visualizado_em' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/chamadas-suporte')
            ->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $naoVisualizada->id)
            ->assertJsonPath('data.0.operario.id', $operario->id);
    }

    public function test_admin_pode_dispensar_chamada(): void
    {
        $admin   = User::factory()->admin()->create();
        $chamada = ChamadaSuporte::create(['origem' => 'manutencao']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/chamadas-suporte/{$chamada->id}/visualizar")
            ->assertOk();

        $this->assertNotNull($chamada->fresh()->visualizado_em);
    }

    public function test_visualizar_chamada_inexistente_retorna_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/chamadas-suporte/999999/visualizar')
            ->assertNotFound();
    }

    public function test_operario_nao_pode_acessar_chamadas_de_admin(): void
    {
        $operario = Operario::factory()->create();

        $this->actingAs($operario->user, 'sanctum')
            ->getJson('/api/admin/chamadas-suporte')
            ->assertForbidden();
    }
}
