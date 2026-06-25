<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\FichaApontamento;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use App\Services\Lote\LoteServiceInterface;
use App\Services\Lote\MockLoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilhaDuplicadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloqueia_segunda_bipagem_quando_bridge_retorna_1_ficha(): void
    {
        $this->app->bind(LoteServiceInterface::class, MockLoteService::class);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
            ])
            ->assertStatus(422);
    }

    public function test_permite_segunda_bipagem_quando_bridge_retorna_2_fichas(): void
    {
        $this->app->instance(LoteServiceInterface::class, MockLoteService::com(2));

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_bloqueia_terceira_bipagem_quando_bridge_retorna_2_fichas(): void
    {
        $this->app->instance(LoteServiceInterface::class, MockLoteService::com(2));

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
            ])
            ->assertStatus(422);
    }
}
