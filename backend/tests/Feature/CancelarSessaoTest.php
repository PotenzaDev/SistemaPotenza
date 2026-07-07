<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\Turno;
use App\Models\User;
use App\Services\Lote\LoteServiceInterface;
use App\Services\RelatorioProducaoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelarSessaoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_cancelar_exclui_apontamentos_nao_finalizados_e_soft_deleta_a_sessao(): void
    {
        $this->mockLoteService();

        $t0 = Carbon::parse('2026-06-25 08:00:00');
        Carbon::setTestNow($t0);
        $this->criarTurnoIntegral($t0);

        [$user, $maquina] = $this->criarOperarioEMaquina();

        $iniciar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])
            ->assertCreated();

        $sessaoId = $iniciar->json('data.id');

        $bipar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', ['cod_peca' => '4501940', 'ordem_lote' => '06854'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'em_setup');

        $apontamentoId = $bipar->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/cancelar')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('sessoes_trabalho', ['id' => $sessaoId]);
        $this->assertDatabaseMissing('apontamentos', ['id' => $apontamentoId]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/sessao/ativa')
            ->assertStatus(404);
    }

    public function test_cancelar_preserva_apontamento_finalizado_e_ele_continua_visivel_em_historico_e_relatorio(): void
    {
        $this->mockLoteService();

        $t0 = Carbon::parse('2026-06-25 08:00:00');
        Carbon::setTestNow($t0);
        $this->criarTurnoIntegral($t0);

        [$user, $maquina] = $this->criarOperarioEMaquina();

        $iniciar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])
            ->assertCreated();

        $sessaoId = $iniciar->json('data.id');

        $apontamentoFinalizado = Apontamento::factory()->finalizado()->create([
            'sessao_trabalho_id' => $sessaoId,
            'etapa_fluxo_id'     => $maquina->etapa_fluxo_id,
        ]);

        $bipar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', ['cod_peca' => '4501940', 'ordem_lote' => '06854'])
            ->assertCreated();

        $apontamentoNaoFinalizadoId = $bipar->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/cancelar')
            ->assertOk();

        $this->assertSoftDeleted('sessoes_trabalho', ['id' => $sessaoId]);
        $this->assertDatabaseHas('apontamentos', ['id' => $apontamentoFinalizado->id, 'status' => 'finalizado']);
        $this->assertDatabaseMissing('apontamentos', ['id' => $apontamentoNaoFinalizadoId]);

        $historico = $this->actingAs($user, 'sanctum')
            ->getJson('/api/apontamento/historico')
            ->assertOk();

        $this->assertContains(
            $apontamentoFinalizado->id,
            collect($historico->json('data'))->pluck('id')->all()
        );

        $relatorio = app(RelatorioProducaoService::class)->relatorioPorDia($t0);
        $this->assertContains($sessaoId, collect($relatorio)->pluck('sessao_id')->all());
    }

    public function test_cancelar_sessao_totalmente_cancelada_nao_aparece_no_relatorio_por_dia(): void
    {
        $this->mockLoteService();

        $t0 = Carbon::parse('2026-06-25 08:00:00');
        Carbon::setTestNow($t0);
        $this->criarTurnoIntegral($t0);

        [$user, $maquina] = $this->criarOperarioEMaquina();

        $iniciar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])
            ->assertCreated();

        $sessaoId = $iniciar->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', ['cod_peca' => '4501940', 'ordem_lote' => '06854'])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/cancelar')
            ->assertOk();

        $relatorio = app(RelatorioProducaoService::class)->relatorioPorDia($t0);
        $this->assertNotContains($sessaoId, collect($relatorio)->pluck('sessao_id')->all());
    }

    public function test_cancelar_sem_sessao_ativa_retorna_erro(): void
    {
        $t0 = Carbon::parse('2026-06-25 08:00:00');
        Carbon::setTestNow($t0);
        $this->criarTurnoIntegral($t0);

        [$user] = $this->criarOperarioEMaquina();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/cancelar')
            ->assertStatus(422);
    }

    private function mockLoteService(): void
    {
        $this->app->bind(LoteServiceInterface::class, fn () => new class implements LoteServiceInterface {
            public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array
            {
                return ['cod_produto' => 'PROD-0001', 'desc_peca' => 'Peça Teste', 'qtde_total' => null];
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
                return ['qtde_total' => null, 'total_pilhas' => 0];
            }

            public function buscarVariantesPorPrefixoLote(string $ordemLote, string $prefixoCod): array
            {
                return [];
            }
        });
    }

    private function criarTurnoIntegral(Carbon $referencia): void
    {
        Turno::create([
            'dia_semana'                      => $referencia->dayOfWeekIso,
            'vigente_desde'                   => $referencia->copy()->subDay()->toDateString(),
            'hora_inicio'                      => '00:00:00',
            'hora_fim'                         => '23:59:00',
            'tolerancia_finalizacao_minutos'   => 0,
            'ativo'                            => true,
        ]);
    }

    /** @return array{0: User, 1: Maquina} */
    private function criarOperarioEMaquina(): array
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        Operario::factory()->create(['user_id' => $user->id]);

        return [$user, $maquina];
    }
}
