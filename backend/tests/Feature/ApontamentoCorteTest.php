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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o fluxo de apontamento de corte (por lote). Assim como em
 * ApontamentoMultiplasPecasLoteTest, o caminho que CRIA o primeiro
 * apontamento de um lote (ApontamentoCorteService::criarApontamento) não é
 * testado aqui porque exige a conexão SQL Server legado (LoteService) — não
 * mockamos Bridge/ficha em teste, o operador valida esse fluxo manualmente.
 * Os casos abaixo cobrem bloqueios e o caminho de "acrescentar ficha a um
 * apontamento de lote já ativo", que não chamam o LoteService.
 */
class ApontamentoCorteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: SessaoTrabalho, 2: Apontamento} */
    private function prepararSessaoComApontamentoDeLoteAtivo(
        string $ordemLote = '12345',
        string $codPeca = '1234567',
        string $status = 'em_producao',
        bool $apontamentoPorLote = true,
    ): array {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true, 'apontamento_por_lote' => $apontamentoPorLote]);
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
            'status'             => $status,
            'producao_inicio'    => now(),
        ]);

        return [$user, $sessao, $apontamento];
    }

    public function test_bloqueia_bipar_quando_maquina_nao_tem_apontamento_por_lote(): void
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true, 'apontamento_por_lote' => false]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento-corte/bipar', [
                'cod_peca'    => '1234567',
                'ordem_lote'  => '12345',
                'pilha'       => 1,
                'qtd_peca'    => 10,
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta máquina não está configurada para apontamento por lote (corte).');

        $this->assertDatabaseCount('apontamentos', 0);
    }

    public function test_bloqueia_bipar_lote_diferente_quando_ja_existe_apontamento_ativo(): void
    {
        [$user] = $this->prepararSessaoComApontamentoDeLoteAtivo(ordemLote: '12345');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento-corte/bipar', [
                'cod_peca'    => '9999999',
                'ordem_lote'  => '99999',
                'pilha'       => 1,
                'qtd_peca'    => 10,
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um apontamento em andamento do lote 12345. Finalize-o antes de iniciar outro lote.');

        $this->assertDatabaseCount('apontamentos', 1);
    }

    /**
     * Acrescentar ficha a um apontamento de lote já ativo agora também passa
     * por LoteService::buscarProdutoCompativel() (SQL Server legado) para
     * validar o cod_produto/cor_codigo lidos do código de barras — como o
     * restante da suíte, não mockamos Bridge/ficha em teste, o operador
     * valida esse caminho manualmente.
     */
    public function test_bipar_peca_diferente_mesmo_lote_acrescenta_ficha_ao_mesmo_apontamento(): void
    {
        $this->markTestSkipped(
            'Requer SQL Server legado (LoteService::buscarProdutoCompativel) — validado manualmente pelo operador.'
        );
    }

    public function test_bloqueia_bipar_pilha_ja_bipada_da_mesma_peca(): void
    {
        [$user, , $apontamento] = $this->prepararSessaoComApontamentoDeLoteAtivo(ordemLote: '12345', codPeca: '1234567');

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '1234567',
            'pilha'          => 1,
            'qtd_peca'       => 10,
            'bipada_at'      => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento-corte/bipar', [
                'cod_peca'    => '1234567',
                'ordem_lote'  => '12345',
                'pilha'       => 1,
                'qtd_peca'    => 10,
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pilha 1 da peça 1234567 já foi bipada neste lote.');
    }

    public function test_bloqueia_bipar_quando_apontamento_do_lote_esta_pausado(): void
    {
        [$user] = $this->prepararSessaoComApontamentoDeLoteAtivo(ordemLote: '12345', status: 'em_pausa_producao');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento-corte/bipar', [
                'cod_peca'    => '1234567',
                'ordem_lote'  => '12345',
                'pilha'       => 2,
                'qtd_peca'    => 10,
                'cod_produto' => '03460',
                'cor_codigo'  => '040',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Apontamento pausado. Retome antes de bipar novas fichas.');
    }
}
