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
use App\Services\RelatorioProducaoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioMaquinaTest extends TestCase
{
    use RefreshDatabase;

    public function test_relatorio_agrega_setup_producao_parado_e_pecas_em_um_periodo_de_dois_dias(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00'); // turno 08:00-17:00
        $terca   = $segunda->copy()->addDay();           // turno 08:00-17:00

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $sessao = $this->criarSessao($maquina, $segunda->copy()->setTime(7, 30));

        $apontamentoSegunda = Apontamento::create([
            'sessao_trabalho_id'        => $sessao->id,
            'etapa_fluxo_id'            => $etapa->id,
            'cod_peca'                  => '1234567',
            'ordem_lote'                => '00001',
            'desc_peca'                 => 'Peça Segunda',
            'cod_produto'               => 'PROD-0001',
            'qtde_total'                => 100,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'              => $segunda->copy()->setTime(8, 0),
            'setup_fim'                 => $segunda->copy()->setTime(9, 0),
            'setup_duracao_segundos'    => 3600,
            'producao_inicio'           => $segunda->copy()->setTime(9, 0),
            'producao_fim'              => $segunda->copy()->setTime(13, 0),
            'producao_duracao_segundos' => 14400,
            'total_pausa_segundos'      => 0,
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamentoSegunda->id,
            'cod_peca'       => '1234567',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'qtd_produzida'  => 50,
            'bipada_at'      => $segunda->copy()->setTime(13, 0),
            'fim_producao'   => $segunda->copy()->setTime(13, 0),
        ]);

        $apontamentoTerca = Apontamento::create([
            'sessao_trabalho_id'        => $sessao->id,
            'etapa_fluxo_id'            => $etapa->id,
            'cod_peca'                  => '7654321',
            'ordem_lote'                => '00002',
            'desc_peca'                 => 'Peça Terça',
            'cod_produto'               => 'PROD-0002',
            'qtde_total'                => 60,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'              => $terca->copy()->setTime(8, 0),
            'setup_fim'                 => $terca->copy()->setTime(8, 30),
            'setup_duracao_segundos'    => 1800,
            'producao_inicio'           => $terca->copy()->setTime(8, 30),
            'producao_fim'              => $terca->copy()->setTime(12, 30),
            'producao_duracao_segundos' => 14400,
            'total_pausa_segundos'      => 0,
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamentoTerca->id,
            'cod_peca'       => '7654321',
            'pilha'          => 1,
            'qtd_peca'       => 30,
            'qtd_produzida'  => 30,
            'bipada_at'      => $terca->copy()->setTime(12, 30),
            'fim_producao'   => $terca->copy()->setTime(12, 30),
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioMaquinasPorPeriodo($segunda, $terca);

        $this->assertSame(2, $relatorio['dias_considerados']);
        $this->assertCount(1, $relatorio['maquinas']);

        $linha = $relatorio['maquinas'][0];
        $this->assertSame($maquina->id, $linha['maquina_id']);
        $this->assertSame(64800, $linha['tempo_turno_segundos']);    // 9h * 2 dias
        $this->assertSame(5400, $linha['tempo_setup_segundos']);     // 3600 + 1800
        $this->assertSame(28800, $linha['tempo_producao_segundos']); // 14400 + 14400
        $this->assertSame(30600, $linha['tempo_parado_segundos']);   // 64800 - 5400 - 28800
        $this->assertSame(80, $linha['qtd_pecas']);                  // 50 + 30
        $this->assertSame(44.4, $linha['percentual_utilizacao']);    // 28800 / 64800 * 100

        $totais = $relatorio['totais'];
        $this->assertSame(64800, $totais['tempo_turno_segundos']);
        $this->assertSame(5400, $totais['tempo_setup_segundos']);
        $this->assertSame(28800, $totais['tempo_producao_segundos']);
        $this->assertSame(30600, $totais['tempo_parado_segundos']);
        $this->assertSame(80, $totais['qtd_pecas']);
    }

    public function test_relatorio_ignora_dias_sem_turno_configurado(): void
    {
        $sexta  = Carbon::parse('2026-06-12 00:00:00'); // turno 08:00-16:30
        $sabado = $sexta->copy()->addDay();             // sem turno (seeder não cadastra sábado/domingo)

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $this->criarSessao($maquina, $sexta->copy()->setTime(7, 30));

        $relatorio = app(RelatorioProducaoService::class)->relatorioMaquinasPorPeriodo($sexta, $sabado);

        $this->assertSame(1, $relatorio['dias_considerados']);
        $this->assertSame(30600, $relatorio['maquinas'][0]['tempo_turno_segundos']); // só sexta: 8h30
    }

    public function test_relatorio_nao_inclui_maquina_inativa(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');

        $etapa = EtapaFluxo::factory()->create(['ativa' => true]);
        Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => false]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioMaquinasPorPeriodo($segunda, $segunda);

        $this->assertSame([], $relatorio['maquinas']);
        $this->assertSame(0, $relatorio['dias_considerados']);
    }

    public function test_gestor_pode_acessar_relatorio_e_operario_recebe_403(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');

        $gestor   = User::factory()->gestor()->create();
        $operario = User::factory()->operario()->create();

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/admin/relatorio-maquinas?' . http_build_query([
                'data_inicio' => $segunda->toDateString(),
                'data_fim'    => $segunda->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['maquinas', 'totais', 'dias_considerados']]);

        $this->actingAs($operario, 'sanctum')
            ->getJson('/api/admin/relatorio-maquinas')
            ->assertForbidden();
    }

    public function test_data_fim_anterior_a_data_inicio_retorna_erro_de_validacao(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/admin/relatorio-maquinas?' . http_build_query([
                'data_inicio' => '2026-06-10',
                'data_fim'    => '2026-06-08',
            ]))
            ->assertStatus(422);
    }

    public function test_periodo_superior_a_um_ano_e_aceito(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/admin/relatorio-maquinas?' . http_build_query([
                'data_inicio' => '2025-01-01',
                'data_fim'    => '2026-06-09',
            ]))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_filtros_retorna_grupos_e_maquinas_ativos(): void
    {
        $gestor = User::factory()->gestor()->create();

        $etapaAtiva    = EtapaFluxo::factory()->create(['ativa' => true]);
        EtapaFluxo::factory()->create(['ativa' => false]);

        Maquina::factory()->create(['etapa_fluxo_id' => $etapaAtiva->id, 'ativa' => true]);
        Maquina::factory()->create(['etapa_fluxo_id' => $etapaAtiva->id, 'ativa' => false]);

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/admin/relatorio-maquinas/filtros')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.grupos')
            ->assertJsonCount(1, 'data.maquinas');
    }

    private function criarSessao(Maquina $maquina, Carbon $inicio): SessaoTrabalho
    {
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);

        return SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'inicio'      => $inicio,
            'fim'         => null,
        ]);
    }
}
