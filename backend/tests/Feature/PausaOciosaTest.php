<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\MotivoPausa;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PausaOciosaTest extends TestCase
{
    use RefreshDatabase;

    public function test_retomar_ociosa_reflete_pausa_fechada_na_resposta(): void
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $motivo   = MotivoPausa::create(['nome' => 'Motivo Teste', 'ativo' => true, 'is_sistema' => false]);

        SessaoTrabalho::create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'inicio'      => now(),
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/pausar-ociosa', ['motivo_pausa_id' => $motivo->id])
            ->assertOk()
            ->assertJsonPath('data.pausa_ociosa.motivo', 'Motivo Teste');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/retomar-ociosa')
            ->assertOk()
            ->assertJsonPath('data.pausa_ociosa', null);
    }

    public function test_dashboard_mostra_pausa_ociosa_para_maquina_sem_apontamento(): void
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $motivo   = MotivoPausa::create(['nome' => 'Motivo Teste', 'ativo' => true, 'is_sistema' => false]);

        SessaoTrabalho::create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'inicio'      => now(),
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/pausar-ociosa', ['motivo_pausa_id' => $motivo->id])
            ->assertOk();

        $admin = User::factory()->admin()->create();

        $resposta = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard');

        $resposta->assertOk();

        $maquinaDashboard = collect($resposta->json('data.maquinas'))->firstWhere('id', $maquina->id);

        $this->assertSame('pausa_ociosa', $maquinaDashboard['status']);
    }
}
