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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApontamentoFinalizacaoParcialTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Apontamento} */
    private function prepararEmProducaoComPecasFaltando(): array
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'ordem_lote'         => '12345',
            'cod_peca'           => '1234567',
            'qtde_total'         => 100,
            'producao_inicio'    => Carbon::now()->subMinutes(30),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '1234567',
            'pilha'          => 1,
            'qtd_peca'       => 40,
            'bipada_at'      => Carbon::now()->subMinutes(20),
        ]);

        return [$user, $apontamento];
    }

    public function test_bloqueia_finalizar_sem_confirmacao_quando_faltam_pecas(): void
    {
        [$user, $apontamento] = $this->prepararEmProducaoComPecasFaltando();
        $ficha = $apontamento->fichas()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/finalizar", [
                'fichas' => [['ficha_id' => $ficha->id, 'qtd_produzida' => 40]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('requiresConfirmation', true)
            ->assertJsonPath('totalBipado', 40)
            ->assertJsonPath('qtdeTotal', 100);

        $this->assertDatabaseHas('apontamentos', [
            'id'     => $apontamento->id,
            'status' => 'em_producao',
        ]);
    }

    public function test_bipar_retoma_apontamento_finalizado_parcial_do_mesmo_lote(): void
    {
        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $userAntigo     = User::factory()->operario()->create();
        $operarioAntigo = Operario::factory()->create(['user_id' => $userAntigo->id]);
        $sessaoAntiga   = SessaoTrabalho::factory()->create([
            'operario_id' => $operarioAntigo->id,
            'maquina_id'  => $maquina->id,
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $origem = Apontamento::factory()->finalizadoParcial()->create([
            'sessao_trabalho_id'         => $sessaoAntiga->id,
            'etapa_fluxo_id'             => $etapa->id,
            'ordem_lote'                 => '12345',
            'cod_peca'                   => '1234567',
            'qtde_total'                 => 100,
            'producao_duracao_segundos'  => 120,
        ]);

        $userNovo     = User::factory()->operario()->create();
        $operarioNovo = Operario::factory()->create(['user_id' => $userNovo->id]);
        $sessaoNova   = SessaoTrabalho::factory()->create([
            'operario_id' => $operarioNovo->id,
            'maquina_id'  => $maquina->id,
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $this->actingAs($userNovo, 'sanctum')
            ->postJson('/api/apontamento/bipar', [
                'cod_peca'    => '1234567',
                'ordem_lote'  => '12345',
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $origem->id)
            ->assertJsonPath('data.status', 'em_producao');

        $this->assertDatabaseHas('apontamentos', [
            'id'                 => $origem->id,
            'status'             => 'em_producao',
            'finalizado_parcial' => false,
            'sessao_trabalho_id' => $sessaoNova->id,
        ]);

        $this->assertDatabaseCount('apontamentos', 1);
    }

    public function test_bloqueia_bipar_quando_lote_peca_ja_finalizado_completo(): void
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        Apontamento::factory()->finalizado()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'ordem_lote'         => '12345',
            'cod_peca'           => '1234567',
            'qtde_total'         => 100,
        ]);

        $userNovo     = User::factory()->operario()->create();
        $operarioNovo = Operario::factory()->create(['user_id' => $userNovo->id]);
        SessaoTrabalho::factory()->create([
            'operario_id' => $operarioNovo->id,
            'maquina_id'  => $maquina->id,
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $this->actingAs($userNovo, 'sanctum')
            ->postJson('/api/apontamento/bipar', [
                'cod_peca'    => '1234567',
                'ordem_lote'  => '12345',
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este lote/peça já foi finalizado integralmente nesta etapa. Não é possível iniciar novo apontamento.');
    }
}
