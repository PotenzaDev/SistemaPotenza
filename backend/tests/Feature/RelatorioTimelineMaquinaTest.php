<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\MotivoPausa;
use App\Models\Operario;
use App\Models\Pausa;
use App\Models\SessaoTrabalho;
use App\Models\User;
use App\Services\TimelineMaquinaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioTimelineMaquinaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_timeline_gera_segmentos_de_setup_producao_pausa_e_para_no_instante_atual(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00'); // turno 08:00-17:00 (seed padrão)
        Carbon::setTestNow($segunda->copy()->setTime(12, 0)); // "agora" no meio do turno

        $etapa = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $motivo = MotivoPausa::create(['nome' => 'Falta de Material', 'ativo' => true, 'is_sistema' => false]);

        $sessao = $this->criarSessao($maquina, $segunda->copy()->setTime(7, 30));

        $apontamento = Apontamento::create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id' => $etapa->id,
            'cod_peca' => '1234567',
            'ordem_lote' => '00001',
            'desc_peca' => 'Peça Teste',
            'cod_produto' => 'PROD-0001',
            'qtde_total' => 100,
            'status' => Apontamento::STATUS_EM_PRODUCAO,
            'setup_inicio' => $segunda->copy()->setTime(8, 0),
            'setup_fim' => $segunda->copy()->setTime(8, 30),
            'producao_inicio' => $segunda->copy()->setTime(8, 30),
            'producao_fim' => null,
        ]);

        Pausa::create([
            'apontamento_id' => $apontamento->id,
            'motivo_pausa_id' => $motivo->id,
            'fase' => 'producao',
            'inicio' => $segunda->copy()->setTime(10, 0),
            'fim' => $segunda->copy()->setTime(10, 30),
            'duracao_segundos' => 1800,
        ]);

        $timeline = app(TimelineMaquinaService::class)->timelineDoDia($segunda, $maquina->id);

        $this->assertNotNull($timeline['turno']);
        $this->assertSame('08:00:00', $timeline['turno']['hora_inicio']);
        $this->assertSame('17:00:00', $timeline['turno']['hora_fim']);

        $this->assertCount(1, $timeline['maquinas']);
        $segmentos = $timeline['maquinas'][0]['segmentos'];

        // Cobre exatamente 08:00 até "agora" (12:00) — nada de "parado" no
        // futuro (12:00-17:00), pois o apontamento ainda está em andamento.
        $this->assertCount(4, $segmentos);
        $this->assertSegmento($segmentos[0], 'setup', $segunda->copy()->setTime(8, 0), $segunda->copy()->setTime(8, 30));
        $this->assertSegmento($segmentos[1], 'producao', $segunda->copy()->setTime(8, 30), $segunda->copy()->setTime(10, 0));
        $this->assertSegmento($segmentos[2], 'pausa', $segunda->copy()->setTime(10, 0), $segunda->copy()->setTime(10, 30));
        $this->assertSegmento($segmentos[3], 'producao', $segunda->copy()->setTime(10, 30), $segunda->copy()->setTime(12, 0));
    }

    public function test_maquina_sem_sessao_no_dia_fica_parada_ate_o_instante_atual(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');
        Carbon::setTestNow($segunda->copy()->setTime(10, 0));

        $etapa = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $timeline = app(TimelineMaquinaService::class)->timelineDoDia($segunda, $maquina->id);

        $segmentos = $timeline['maquinas'][0]['segmentos'];

        $this->assertCount(1, $segmentos);
        $this->assertSegmento($segmentos[0], 'parado', $segunda->copy()->setTime(8, 0), $segunda->copy()->setTime(10, 0));
    }

    public function test_dia_sem_turno_configurado_retorna_turno_nulo_e_sem_maquinas(): void
    {
        $sabado = Carbon::parse('2026-06-13 00:00:00'); // seeder não cadastra sábado

        $etapa = EtapaFluxo::factory()->create(['ativa' => true]);
        Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $timeline = app(TimelineMaquinaService::class)->timelineDoDia($sabado);

        $this->assertNull($timeline['turno']);
        $this->assertSame([], $timeline['maquinas']);
    }

    public function test_sabado_sem_turno_configurado_com_movimentacao_real_usa_janela_de_fallback(): void
    {
        $sabado = Carbon::parse('2026-06-13 00:00:00'); // seeder não cadastra sábado
        Carbon::setTestNow($sabado->copy()->setTime(9, 0));

        $etapa = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $sessao = $this->criarSessao($maquina, $sabado->copy()->setTime(7, 0));

        Apontamento::create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id' => $etapa->id,
            'cod_peca' => '1234567',
            'ordem_lote' => '00001',
            'desc_peca' => 'Peça Sábado',
            'cod_produto' => 'PROD-0001',
            'qtde_total' => 10,
            'status' => Apontamento::STATUS_EM_PRODUCAO,
            'setup_inicio' => $sabado->copy()->setTime(7, 0),
            'setup_fim' => $sabado->copy()->setTime(7, 30),
            'producao_inicio' => $sabado->copy()->setTime(7, 30),
            'producao_fim' => null,
        ]);

        $timeline = app(TimelineMaquinaService::class)->timelineDoDia($sabado, $maquina->id);

        $this->assertNotNull($timeline['turno']);
        $this->assertSame('06:00:00', $timeline['turno']['hora_inicio']);
        $this->assertSame('12:00:00', $timeline['turno']['hora_fim']);

        $this->assertCount(1, $timeline['maquinas']);
        $segmentos = $timeline['maquinas'][0]['segmentos'];

        // 06:00-07:00 parado, 07:00-07:30 setup, 07:30-09:00 (agora) produção.
        $this->assertCount(3, $segmentos);
        $this->assertSegmento($segmentos[0], 'parado', $sabado->copy()->setTime(6, 0), $sabado->copy()->setTime(7, 0));
        $this->assertSegmento($segmentos[1], 'setup', $sabado->copy()->setTime(7, 0), $sabado->copy()->setTime(7, 30));
        $this->assertSegmento($segmentos[2], 'producao', $sabado->copy()->setTime(7, 30), $sabado->copy()->setTime(9, 0));
    }

    public function test_gestor_pode_acessar_endpoint_e_operario_recebe_403(): void
    {
        $gestor = User::factory()->gestor()->create();
        $operario = User::factory()->operario()->create();

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/admin/relatorio-timeline-maquinas?'.http_build_query([
                'data' => '2026-06-08',
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['turno', 'maquinas']]);

        $this->actingAs($operario, 'sanctum')
            ->getJson('/api/admin/relatorio-timeline-maquinas')
            ->assertForbidden();
    }

    public function test_filtro_por_maquina_via_query_string_nao_gera_erro_de_tipo(): void
    {
        // Parâmetros de query chegam como string ("1", não 1) — o controller
        // precisa converter antes de repassar para o service (?int).
        $gestor = User::factory()->gestor()->create();
        $etapa = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/admin/relatorio-timeline-maquinas?'.http_build_query([
                'data' => '2026-06-08',
                'maquina_id' => (string) $maquina->id,
                'grupo_id' => (string) $etapa->id,
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.maquinas');
    }

    private function assertSegmento(array $segmento, string $tipo, Carbon $inicio, Carbon $fim): void
    {
        $this->assertSame($tipo, $segmento['tipo']);
        $this->assertTrue(Carbon::parse($segmento['inicio'])->equalTo($inicio), "início esperado {$inicio} para segmento {$tipo}, recebido {$segmento['inicio']}");
        $this->assertTrue(Carbon::parse($segmento['fim'])->equalTo($fim), "fim esperado {$fim} para segmento {$tipo}, recebido {$segmento['fim']}");
    }

    private function criarSessao(Maquina $maquina, Carbon $inicio): SessaoTrabalho
    {
        $user = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);

        return SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id' => $maquina->id,
            'inicio' => $inicio,
            'fim' => null,
        ]);
    }
}
