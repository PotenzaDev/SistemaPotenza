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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PausaSessaoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_pausar_sessao_forca_nova_janela_de_setup_e_acumula_tempo_na_retomada(): void
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
        });

        $t0 = Carbon::parse('2026-06-25 08:00:00');
        Carbon::setTestNow($t0);

        Turno::create([
            'dia_semana'                      => $t0->dayOfWeekIso,
            'vigente_desde'                   => $t0->copy()->subDay()->toDateString(),
            'hora_inicio'                      => '00:00:00',
            'hora_fim'                         => '23:59:00',
            'tolerancia_finalizacao_minutos'   => 0,
            'ativo'                            => true,
        ]);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);

        $iniciar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $sessaoId = $iniciar->json('data.id');

        $bipar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'em_setup');

        $apontamentoId = $bipar->json('data.id');

        // 10 minutos de setup, depois o operário pausa a sessão.
        $t1 = $t0->copy()->addMinutes(10);
        Carbon::setTestNow($t1);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/pausar')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('sessoes_trabalho', [
            'id'     => $sessaoId,
            'status' => 'pausada',
        ]);

        $this->assertDatabaseHas('apontamentos', [
            'id'     => $apontamentoId,
            'status' => 'em_pausa_setup',
        ]);

        $this->assertDatabaseHas('pausas', [
            'apontamento_id' => $apontamentoId,
            'fase'           => 'setup',
            'fim'            => null,
        ]);

        // Operário ausente por 30 minutos, depois retoma na mesma máquina.
        $t2 = $t1->copy()->addMinutes(30);
        Carbon::setTestNow($t2);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id, 'sessao_pausada_id' => $sessaoId])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $sessaoId)
            ->assertJsonPath('data.ativa', true);

        $this->assertDatabaseHas('sessoes_trabalho', [
            'id'     => $sessaoId,
            'status' => 'ativa',
            'fim'    => null,
        ]);

        $apontamento = Apontamento::find($apontamentoId);
        $this->assertSame('em_setup', $apontamento->status);
        $this->assertTrue($apontamento->setup_inicio->equalTo($t2));
        $this->assertNull($apontamento->setup_fim);
        $this->assertSame(1800, $apontamento->total_pausa_segundos);

        $pausaSessao = $apontamento->pausas()->where('fase', 'setup')->whereNotNull('fim')->first();
        $this->assertNotNull($pausaSessao);
        $this->assertSame(1800, $pausaSessao->duracao_segundos);

        // Mais 5 minutos de setup, agora finaliza.
        $t3 = $t2->copy()->addMinutes(5);
        Carbon::setTestNow($t3);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamentoId}/finalizar-setup")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'aguardando_producao');

        $apontamento->refresh();

        // 10 min (primeira janela) + 5 min (segunda janela) = 900s, sem contar os 30 min de ausência.
        $this->assertSame(900, $apontamento->setup_duracao_segundos);
    }

    public function test_iniciar_sem_escolher_sessao_cria_nova_e_preserva_a_pausada(): void
    {
        $this->mockLoteService();

        $t0 = Carbon::parse('2026-06-25 08:00:00');
        Carbon::setTestNow($t0);
        $this->criarTurnoIntegral($t0);

        [$user, $maquina] = $this->criarOperarioEMaquina();

        $sessaoId = $this->iniciarSessaoEBiparPilha($user, $maquina);

        $this->actingAs($user, 'sanctum')->postJson('/api/sessao/pausar')->assertOk();

        // Sem informar sessao_pausada_id: deve criar uma sessão nova, não retomar a pausada.
        $nova = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])
            ->assertCreated()
            ->assertJsonPath('data.ativa', true);

        $this->assertNotSame($sessaoId, $nova->json('data.id'));

        $this->assertDatabaseHas('sessoes_trabalho', ['id' => $sessaoId, 'status' => 'pausada']);
        $this->assertDatabaseHas('sessoes_trabalho', ['id' => $nova->json('data.id'), 'status' => 'ativa']);
    }

    public function test_listar_sessoes_pausadas_retorna_todas_as_pendencias_da_maquina(): void
    {
        $this->mockLoteService();

        $t0 = Carbon::parse('2026-06-25 08:00:00');
        Carbon::setTestNow($t0);
        $this->criarTurnoIntegral($t0);

        [$user, $maquina] = $this->criarOperarioEMaquina();

        // Pausa a primeira sessão com um lote em andamento (06854).
        $sessaoA = $this->iniciarSessaoEBiparPilha($user, $maquina, '06854');
        $this->actingAs($user, 'sanctum')->postJson('/api/sessao/pausar')->assertOk();

        // Como só pode haver 1 apontamento ativo por operário, a segunda sessão
        // pausada aqui não tem lote bipado — ainda assim deve aparecer na lista.
        $sessaoB = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')->postJson('/api/sessao/pausar')->assertOk();

        $resposta = $this->actingAs($user, 'sanctum')
            ->getJson('/api/sessao/pausadas?' . http_build_query(['maquina_id' => $maquina->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $porId = collect($resposta->json('data'))->keyBy('id');
        $this->assertSame('06854', $porId[$sessaoA]['ordem_lote']);
        $this->assertNull($porId[$sessaoB]['ordem_lote']);
    }

    public function test_retomar_sessao_pausada_de_outro_operario_e_rejeitado(): void
    {
        $this->mockLoteService();

        $t0 = Carbon::parse('2026-06-25 08:00:00');
        Carbon::setTestNow($t0);
        $this->criarTurnoIntegral($t0);

        [$userA, $maquina] = $this->criarOperarioEMaquina();
        $sessaoA = $this->iniciarSessaoEBiparPilha($userA, $maquina);
        $this->actingAs($userA, 'sanctum')->postJson('/api/sessao/pausar')->assertOk();

        $userB     = User::factory()->operario()->create();
        $operarioB = Operario::factory()->create(['user_id' => $userB->id]);

        $this->actingAs($userB, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id, 'sessao_pausada_id' => $sessaoA])
            ->assertStatus(404);
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

    private function iniciarSessaoEBiparPilha(User $user, Maquina $maquina, string $ordemLote = '06854'): int
    {
        $iniciar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', ['cod_peca' => '4501940', 'ordem_lote' => $ordemLote])
            ->assertCreated();

        return $iniciar->json('data.id');
    }
}
