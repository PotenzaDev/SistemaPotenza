<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\FichaApontamento;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\RegraMaquina;
use App\Models\SessaoTrabalho;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApontamentoFinalizacaoParcialPorMaquinaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Apontamento} */
    private function prepararEmProducaoComPecasFaltando(bool $permiteFinalizacaoParcial): array
    {
        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        RegraMaquina::create([
            'maquina_id'                   => $maquina->id,
            'possui_setup'                 => true,
            'possui_producao'              => true,
            'permite_multiplas_passagens'  => true,
            'limite_passagens'             => null,
            'permite_finalizacao_parcial'  => $permiteFinalizacaoParcial,
        ]);

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

    public function test_bloqueia_finalizacao_parcial_mesmo_confirmando_quando_maquina_nao_permite(): void
    {
        [$user, $apontamento] = $this->prepararEmProducaoComPecasFaltando(permiteFinalizacaoParcial: false);
        $ficha = $apontamento->fichas()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/finalizar", [
                'fichas'           => [['ficha_id' => $ficha->id, 'qtd_produzida' => 40]],
                'confirmar_parcial' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta máquina não permite finalização parcial. Bipe todas as peças e cores antes de finalizar. Bipado: 40 de 100 peças.');

        $this->assertDatabaseHas('apontamentos', [
            'id'     => $apontamento->id,
            'status' => 'em_producao',
        ]);
    }

    public function test_nao_bloqueia_por_regra_de_maquina_quando_permite_finalizacao_parcial(): void
    {
        // Sem Bridge/dados de ficha fake: verificamos que a máquina com a flag
        // ligada não é barrada pela NOVA regra (BusinessException 422 imediata) —
        // continua caindo no fluxo padrão de confirmação (409), que é quem decide
        // se segue para a finalização de fato. O caminho de sucesso completo
        // (que chama atualizarHistoricoLote → SQL Server legado) é validado
        // manualmente, não em teste automatizado.
        [$user, $apontamento] = $this->prepararEmProducaoComPecasFaltando(permiteFinalizacaoParcial: true);
        $ficha = $apontamento->fichas()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/finalizar", [
                'fichas' => [['ficha_id' => $ficha->id, 'qtd_produzida' => 40]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('requiresConfirmation', true);

        $this->assertDatabaseHas('apontamentos', [
            'id'     => $apontamento->id,
            'status' => 'em_producao',
        ]);
    }
}
