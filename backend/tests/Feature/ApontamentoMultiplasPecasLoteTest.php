<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\MotivoPausa;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a lógica de bloqueio/permissão de bipar() para múltiplas peças no
 * mesmo lote (task: mesmo lote pode ter peças-base diferentes bipadas em
 * paralelo na mesma sessão). O caminho de SUCESSO de bipar() (criar um novo
 * apontamento para a peça nova) não é coberto aqui porque exige a conexão
 * SQL Server legado (LoteService) — não mockamos Bridge/ficha em teste, o
 * operador valida esse fluxo manualmente. Os três casos de bloqueio abaixo
 * retornam antes de qualquer chamada à Bridge, então são seguros de testar.
 */
class ApontamentoMultiplasPecasLoteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: SessaoTrabalho} */
    private function prepararSessaoComApontamentoAtivo(string $ordemLote, string $codPeca, string $status = 'em_producao'): array
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

        Apontamento::factory()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'ordem_lote'         => $ordemLote,
            'cod_peca'           => $codPeca,
            'status'             => $status,
        ]);

        return [$user, $sessao];
    }

    public function test_bloqueia_bipar_peca_com_mesmo_prefixo_ja_ativa_no_lote(): void
    {
        [$user] = $this->prepararSessaoComApontamentoAtivo(ordemLote: '12345', codPeca: '1234567');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', [
                // Mesmo prefixo de 5 dígitos (12345) do apontamento já ativo — só a cor muda.
                'cod_peca'   => '1234599',
                'ordem_lote' => '12345',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um apontamento ativo para esta peça neste lote. Continue bipando fichas nele.');

        $this->assertDatabaseCount('apontamentos', 1);
    }

    public function test_bloqueia_bipar_lote_diferente_quando_ja_existe_apontamento_ativo(): void
    {
        [$user] = $this->prepararSessaoComApontamentoAtivo(ordemLote: '12345', codPeca: '1234567');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', [
                'cod_peca'   => '9999999',
                'ordem_lote' => '99999',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um apontamento em andamento do lote 12345. Finalize-o antes de iniciar outro lote.');

        $this->assertDatabaseCount('apontamentos', 1);
    }

    public function test_lista_todos_os_apontamentos_ativos_da_sessao(): void
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

        $a = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'ordem_lote'         => '12345',
            'cod_peca'           => '1234567',
        ]);
        $b = Apontamento::factory()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'ordem_lote'         => '12345',
            'cod_peca'           => '5555567',
            'status'             => 'aguardando_producao',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/apontamento/ativos')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids);
    }

    public function test_pausar_sessao_pausa_todos_os_apontamentos_ativos_do_lote(): void
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

        MotivoPausa::create(['nome' => 'Pausa de Sessão', 'ativo' => true, 'is_sistema' => true]);

        $a = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'ordem_lote'         => '12345',
            'cod_peca'           => '1234567',
        ]);
        $b = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'ordem_lote'         => '12345',
            'cod_peca'           => '5555567',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/pausar')
            ->assertOk();

        $this->assertDatabaseHas('apontamentos', ['id' => $a->id, 'status' => 'em_pausa_producao']);
        $this->assertDatabaseHas('apontamentos', ['id' => $b->id, 'status' => 'em_pausa_producao']);
        $this->assertDatabaseHas('pausas', ['apontamento_id' => $a->id, 'fase' => 'producao']);
        $this->assertDatabaseHas('pausas', ['apontamento_id' => $b->id, 'fase' => 'producao']);
    }
}
