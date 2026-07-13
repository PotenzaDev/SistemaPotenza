<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\RegraMaquina;
use App\Models\Turno;
use App\Models\User;
use App\Services\Lote\LoteServiceInterface;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApontamentoFluxoRegrasMaquinaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function fakeLoteService(int $qtdeTotal = 50): void
    {
        $this->app->bind(LoteServiceInterface::class, fn () => new class($qtdeTotal) implements LoteServiceInterface {
            public function __construct(private readonly int $qtdeTotal) {}

            public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array
            {
                return ['cod_produto' => 'PROD-0001', 'desc_peca' => 'Peça Teste', 'qtde_total' => $this->qtdeTotal];
            }

            public function buscarFtecPecaPilha(string $codPeca): ?int
            {
                return null;
            }

            public function contarFichasLote(string $ordemLote, string $codPeca): int
            {
                return 1;
            }

            public function buscarTotaisPorPrefixoLote(string $ordemLote, string $prefixoCod): array
            {
                return ['qtde_total' => $this->qtdeTotal, 'total_pilhas' => 0];
            }

            public function buscarVariantesPorPrefixoLote(string $ordemLote, string $prefixoCod): array
            {
                return [];
            }
        });
    }

    /** @return array{0: User, 1: Maquina} */
    private function prepararUsuarioEMaquina(?RegraMaquina $regra): array
    {
        Turno::create([
            'dia_semana'                    => now()->dayOfWeekIso,
            'vigente_desde'                  => now()->subDay()->toDateString(),
            'hora_inicio'                     => '00:00:00',
            'hora_fim'                        => '23:59:00',
            'tolerancia_finalizacao_minutos'  => 0,
            'ativo'                           => true,
        ]);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        Operario::factory()->create(['user_id' => $user->id]);

        if ($regra) {
            RegraMaquina::create(array_merge(['maquina_id' => $maquina->id], $regra->toArray()));
        }

        return [$user, $maquina];
    }

    public function test_bipar_pula_setup_quando_maquina_nao_possui_setup(): void
    {
        $this->fakeLoteService();
        [$user, $maquina] = $this->prepararUsuarioEMaquina(
            new RegraMaquina(['possui_setup' => false, 'possui_producao' => true, 'permite_multiplas_passagens' => true])
        );

        $this->actingAs($user, 'sanctum')->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', ['cod_peca' => '4501940', 'ordem_lote' => '06854'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'aguardando_producao')
            ->assertJsonPath('data.setup_inicio', null);
    }

    public function test_bipar_mantem_setup_quando_maquina_possui_setup(): void
    {
        $this->fakeLoteService();
        [$user, $maquina] = $this->prepararUsuarioEMaquina(
            new RegraMaquina(['possui_setup' => true, 'possui_producao' => true, 'permite_multiplas_passagens' => true])
        );

        $this->actingAs($user, 'sanctum')->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', ['cod_peca' => '4501940', 'ordem_lote' => '06854'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'em_setup');
    }

    public function test_finalizar_sem_producao_bloqueia_quando_maquina_exige_producao(): void
    {
        [$user, $maquina] = $this->prepararUsuarioEMaquina(
            new RegraMaquina(['possui_setup' => false, 'possui_producao' => true, 'permite_multiplas_passagens' => true])
        );
        $apontamento = $this->criarApontamentoAguardandoProducao($user, $maquina, 50);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/finalizar-sem-producao")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta máquina exige bipagem de fichas antes de finalizar.');
    }

    public function test_finalizar_sem_producao_funciona_quando_maquina_nao_exige_producao(): void
    {
        [$user, $maquina] = $this->prepararUsuarioEMaquina(
            new RegraMaquina(['possui_setup' => false, 'possui_producao' => false, 'permite_multiplas_passagens' => true])
        );
        $apontamento = $this->criarApontamentoAguardandoProducao($user, $maquina, 50);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/finalizar-sem-producao")
            ->assertOk()
            ->assertJsonPath('data.status', 'finalizado');

        $this->assertDatabaseHas('fichas_apontamento', [
            'apontamento_id' => $apontamento->id,
            'qtd_peca'       => 50,
            'qtd_produzida'  => 50,
        ]);
    }

    public function test_finalizar_sem_producao_falha_se_apontamento_nao_esta_aguardando_producao(): void
    {
        [$user, $maquina] = $this->prepararUsuarioEMaquina(
            new RegraMaquina(['possui_setup' => true, 'possui_producao' => false, 'permite_multiplas_passagens' => true])
        );

        $this->fakeLoteService();
        $this->actingAs($user, 'sanctum')->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])->assertCreated();
        $bipar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', ['cod_peca' => '4501940', 'ordem_lote' => '06854'])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$bipar->json('data.id')}/finalizar-sem-producao")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Apontamento não está aguardando produção.');
    }

    private function criarApontamentoAguardandoProducao(User $user, Maquina $maquina, int $qtdeTotal): Apontamento
    {
        $this->fakeLoteService($qtdeTotal);

        $this->actingAs($user, 'sanctum')->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])->assertCreated();
        $bipar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', ['cod_peca' => '4501940', 'ordem_lote' => '06854'])
            ->assertCreated();

        return Apontamento::findOrFail($bipar->json('data.id'));
    }
}
