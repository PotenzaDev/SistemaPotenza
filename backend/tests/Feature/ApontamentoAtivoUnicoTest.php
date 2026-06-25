<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApontamentoAtivoUnicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_operario_nao_pode_bipar_com_apontamento_em_setup_ativo(): void
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);

        Apontamento::factory()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'status'             => 'em_setup',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', [
                'cod_peca'   => '9999999',
                'ordem_lote' => '99999',
                'qtd_peca'   => 10,
                'pilha'      => 2,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_operario_nao_pode_bipar_com_apontamento_em_producao_ativo(): void
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);

        Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', [
                'cod_peca'   => '9999999',
                'ordem_lote' => '99999',
                'qtd_peca'   => 10,
                'pilha'      => 2,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
