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

/**
 * Cobre o fluxo de apontamento de coladeira. Assim como em ApontamentoCorteTest,
 * o caminho que CRIA o apontamento (ApontamentoColadeiraService::bipar ->
 * LoteService::buscarPorOrdemLote) não é testado aqui porque exige o SQL
 * Server legado — não mockamos Bridge/ficha em teste, o operador valida esse
 * fluxo manualmente. O mesmo vale para biparFicha(), que chama
 * LoteService::buscarProdutoCompativel() antes de qualquer outra checagem
 * (inclusive antes de calcular metros_lineares_borda) — o cálculo em si é
 * validado manualmente junto com o restante do fluxo.
 */
class ApontamentoColadeiraTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: SessaoTrabalho, 2: Apontamento} */
    private function prepararSessaoComApontamentoAtivo(
        string $ordemLote = '12345',
        string $codPeca = '1234567',
        string $status = 'aguardando_producao',
        bool $calculaMetragemBorda = true,
    ): array {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true, 'calcula_metragem_borda' => $calculaMetragemBorda]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $apontamento = Apontamento::factory()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'ordem_lote'         => $ordemLote,
            'cod_peca'           => $codPeca,
            'qtde_total'         => 100,
            'comprimento'        => 2.0,
            'largura'            => 0.6,
            'qtd_bor_comp'       => 2,
            'qtd_bor_larg'       => 1,
            'status'             => $status,
        ]);

        return [$user, $sessao, $apontamento];
    }

    public function test_bloqueia_bipar_quando_maquina_nao_calcula_metragem_borda(): void
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true, 'calcula_metragem_borda' => false]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento-coladeira/bipar', [
                'cod_peca'    => '1234567',
                'ordem_lote'  => '12345',
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta máquina não está configurada para apontamento de coladeira.');

        $this->assertDatabaseCount('apontamentos', 0);
    }

    public function test_bloqueia_bipar_lote_diferente_quando_ja_existe_apontamento_ativo(): void
    {
        [$user] = $this->prepararSessaoComApontamentoAtivo(ordemLote: '12345');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento-coladeira/bipar', [
                'cod_peca'    => '9999999',
                'ordem_lote'  => '99999',
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um apontamento em andamento do lote 12345. Finalize-o antes de iniciar outro lote.');

        $this->assertDatabaseCount('apontamentos', 1);
    }

    public function test_bloqueia_bipar_ficha_quando_apontamento_nao_esta_aguardando_ou_em_producao(): void
    {
        [$user, , $apontamento] = $this->prepararSessaoComApontamentoAtivo(status: 'em_setup');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento-coladeira/{$apontamento->id}/bipar-ficha", [
                'cod_peca'    => '1234567',
                'ordem_lote'  => '12345',
                'qtd_peca'    => 10,
                'pilha'       => 1,
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Apontamento não está aguardando ou em produção.');
    }

    public function test_bloqueia_bipar_ficha_de_lote_diferente_do_apontamento_ativo(): void
    {
        [$user, , $apontamento] = $this->prepararSessaoComApontamentoAtivo(ordemLote: '12345', status: 'aguardando_producao');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento-coladeira/{$apontamento->id}/bipar-ficha", [
                'cod_peca'    => '1234567',
                'ordem_lote'  => '99999',
                'qtd_peca'    => 10,
                'pilha'       => 1,
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta ficha é do lote 99999, mas o apontamento ativo é do lote 12345.');
    }

    public function test_calcula_metros_lineares_borda_ao_bipar_ficha(): void
    {
        $this->markTestSkipped(
            'Requer SQL Server legado (LoteService::buscarProdutoCompativel, chamado antes do cálculo em biparFicha) — validado manualmente pelo operador.'
        );
    }
}
