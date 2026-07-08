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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_relatorio_ignora_dia_sem_turno_configurado_e_sem_movimentacao(): void
    {
        $sexta  = Carbon::parse('2026-06-12 00:00:00'); // turno 08:00-16:30, com movimentação real
        $sabado = $sexta->copy()->addDay();             // sem turno (seeder não cadastra sábado/domingo) e sem movimentação

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $sessao = $this->criarSessao($maquina, $sexta->copy()->setTime(7, 30));

        Apontamento::create([
            'sessao_trabalho_id'     => $sessao->id,
            'etapa_fluxo_id'         => $etapa->id,
            'cod_peca'               => '1234567',
            'ordem_lote'             => '00001',
            'desc_peca'              => 'Peça Sexta',
            'cod_produto'            => 'PROD-0001',
            'qtde_total'             => 10,
            'status'                 => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'           => $sexta->copy()->setTime(8, 0),
            'setup_fim'              => $sexta->copy()->setTime(9, 0),
            'setup_duracao_segundos' => 3600,
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioMaquinasPorPeriodo($sexta, $sabado);

        $this->assertSame(1, $relatorio['dias_considerados']);
        $this->assertSame(1, $relatorio['maquinas'][0]['dias_com_movimentacao']);
        $this->assertSame(30600, $relatorio['maquinas'][0]['tempo_turno_segundos']); // só sexta: 8h30
    }

    public function test_relatorio_inclui_sabado_sem_turno_configurado_quando_ha_movimentacao_real(): void
    {
        $sabado = Carbon::parse('2026-06-13 00:00:00'); // seeder não cadastra sábado

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $sessao = $this->criarSessao($maquina, $sabado->copy()->setTime(7, 0));

        Apontamento::create([
            'sessao_trabalho_id'        => $sessao->id,
            'etapa_fluxo_id'            => $etapa->id,
            'cod_peca'                  => '1234567',
            'ordem_lote'                => '00001',
            'desc_peca'                 => 'Peça Sábado',
            'cod_produto'               => 'PROD-0001',
            'qtde_total'                => 10,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'              => $sabado->copy()->setTime(7, 0),
            'setup_fim'                 => $sabado->copy()->setTime(8, 0),
            'setup_duracao_segundos'    => 3600,
            'producao_inicio'           => $sabado->copy()->setTime(8, 0),
            'producao_fim'              => $sabado->copy()->setTime(10, 0),
            'producao_duracao_segundos' => 7200,
            'total_pausa_segundos'      => 0,
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioMaquinasPorPeriodo($sabado, $sabado);

        $this->assertSame(1, $relatorio['dias_considerados']);
        $linha = $relatorio['maquinas'][0];
        $this->assertSame(1, $linha['dias_com_movimentacao']);
        // Janela de fallback 06:00-12:00 (sem turno cadastrado para sábado).
        $this->assertSame(21600, $linha['tempo_turno_segundos']); // 6h
        $this->assertSame(3600, $linha['tempo_setup_segundos']);
        $this->assertSame(7200, $linha['tempo_producao_segundos']);
    }

    public function test_relatorio_usa_turno_informado_pelo_operario_em_vez_do_fallback(): void
    {
        $sabado = Carbon::parse('2026-06-13 00:00:00'); // seeder não cadastra sábado

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id'            => $operario->id,
            'maquina_id'             => $maquina->id,
            'inicio'                 => $sabado->copy()->setTime(14, 0),
            'fim'                    => null,
            'turno_informado_inicio' => '14:00:00',
            'turno_informado_fim'    => '18:00:00',
        ]);

        Apontamento::create([
            'sessao_trabalho_id'        => $sessao->id,
            'etapa_fluxo_id'            => $etapa->id,
            'cod_peca'                  => '7778889',
            'ordem_lote'                => '00006',
            'desc_peca'                 => 'Peça Turno Informado',
            'cod_produto'               => 'PROD-0006',
            'qtde_total'                => 10,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'producao_inicio'           => $sabado->copy()->setTime(14, 0),
            'producao_fim'              => $sabado->copy()->setTime(17, 0),
            'producao_duracao_segundos' => 10800,
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioMaquinasPorPeriodo($sabado, $sabado);

        $this->assertSame(1, $relatorio['dias_considerados']);
        $linha = $relatorio['maquinas'][0];
        // Janela informada 14:00-18:00 (4h) — não a de fallback 06:00-12:00.
        $this->assertSame(14400, $linha['tempo_turno_segundos']);
        $this->assertSame(10800, $linha['tempo_producao_segundos']);
    }

    public function test_relatorio_exclui_dia_de_semana_com_turno_ativo_mas_sem_movimentacao(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00'); // turno ativo 08:00-17:00, feriado sem apontamentos

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        // Sessão aberta (ex.: operário bateu ponto) mas sem nenhum apontamento.
        $this->criarSessao($maquina, $segunda->copy()->setTime(7, 30));

        $relatorio = app(RelatorioProducaoService::class)->relatorioMaquinasPorPeriodo($segunda, $segunda);

        $this->assertSame(0, $relatorio['dias_considerados']);
        $this->assertSame(0, $relatorio['maquinas'][0]['dias_com_movimentacao']);
        $this->assertSame(0, $relatorio['maquinas'][0]['tempo_turno_segundos']);
    }

    public function test_relatorio_conta_dias_com_movimentacao_de_forma_independente_por_maquina(): void
    {
        $sexta  = Carbon::parse('2026-06-12 00:00:00'); // turno 08:00-16:30
        $sabado = $sexta->copy()->addDay();             // sem turno cadastrado

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquinaA = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $maquinaB = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        // Máquina A trabalha sexta e sábado. Máquina B só trabalha sexta.
        $sessaoA = $this->criarSessao($maquinaA, $sexta->copy()->setTime(7, 0));
        Apontamento::create([
            'sessao_trabalho_id'     => $sessaoA->id,
            'etapa_fluxo_id'         => $etapa->id,
            'cod_peca'               => '1111111',
            'ordem_lote'             => '00001',
            'desc_peca'              => 'Peça A Sexta',
            'cod_produto'            => 'PROD-0001',
            'qtde_total'             => 10,
            'status'                 => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'           => $sexta->copy()->setTime(8, 0),
            'setup_fim'              => $sexta->copy()->setTime(9, 0),
            'setup_duracao_segundos' => 3600,
        ]);
        Apontamento::create([
            'sessao_trabalho_id'     => $sessaoA->id,
            'etapa_fluxo_id'         => $etapa->id,
            'cod_peca'               => '1111112',
            'ordem_lote'             => '00002',
            'desc_peca'              => 'Peça A Sábado',
            'cod_produto'            => 'PROD-0001',
            'qtde_total'             => 10,
            'status'                 => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'           => $sabado->copy()->setTime(7, 0),
            'setup_fim'              => $sabado->copy()->setTime(8, 0),
            'setup_duracao_segundos' => 3600,
        ]);

        $sessaoB = $this->criarSessao($maquinaB, $sexta->copy()->setTime(7, 0));
        Apontamento::create([
            'sessao_trabalho_id'     => $sessaoB->id,
            'etapa_fluxo_id'         => $etapa->id,
            'cod_peca'               => '2222222',
            'ordem_lote'             => '00003',
            'desc_peca'              => 'Peça B Sexta',
            'cod_produto'            => 'PROD-0002',
            'qtde_total'             => 10,
            'status'                 => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'           => $sexta->copy()->setTime(8, 0),
            'setup_fim'              => $sexta->copy()->setTime(9, 0),
            'setup_duracao_segundos' => 3600,
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioMaquinasPorPeriodo($sexta, $sabado);

        // Período tem movimentação em 2 dias distintos (sexta e sábado), somando qualquer máquina.
        $this->assertSame(2, $relatorio['dias_considerados']);

        $linhas = collect($relatorio['maquinas'])->keyBy('maquina_id');
        $this->assertSame(2, $linhas[$maquinaA->id]['dias_com_movimentacao']);
        $this->assertSame(1, $linhas[$maquinaB->id]['dias_com_movimentacao']);
    }

    public function test_alterar_turno_hoje_nao_altera_relatorio_de_dia_passado(): void
    {
        Carbon::setTestNow('2026-06-15'); // "hoje" — depois do dia que será reportado

        $segundaPassada = Carbon::parse('2026-06-08 00:00:00'); // turno original: 08:00-17:00

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $sessao = $this->criarSessao($maquina, $segundaPassada->copy()->setTime(7, 30));

        Apontamento::create([
            'sessao_trabalho_id'        => $sessao->id,
            'etapa_fluxo_id'            => $etapa->id,
            'cod_peca'                  => '1234567',
            'ordem_lote'                => '00001',
            'desc_peca'                 => 'Peça Teste',
            'cod_produto'               => 'PROD-0001',
            'qtde_total'                => 100,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'              => $segundaPassada->copy()->setTime(8, 0),
            'setup_fim'                 => $segundaPassada->copy()->setTime(9, 0),
            'setup_duracao_segundos'    => 3600,
            'producao_inicio'           => $segundaPassada->copy()->setTime(9, 0),
            'producao_fim'              => $segundaPassada->copy()->setTime(13, 0),
            'producao_duracao_segundos' => 14400,
            'total_pausa_segundos'      => 0,
        ]);

        $relatorioAntes = app(RelatorioProducaoService::class)
            ->relatorioMaquinasPorPeriodo($segundaPassada, $segundaPassada);

        // Admin muda o turno de segunda-feira "hoje" (06:00-20:00).
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '06:00',
                'hora_fim'                        => '20:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertOk();

        $relatorioDepois = app(RelatorioProducaoService::class)
            ->relatorioMaquinasPorPeriodo($segundaPassada, $segundaPassada);

        // O relatório do dia passado não pode mudar com a edição de hoje.
        $this->assertSame($relatorioAntes, $relatorioDepois);
        $this->assertSame(32400, $relatorioDepois['maquinas'][0]['tempo_turno_segundos']); // 9h, turno original

        // Movimentação real "hoje" (segunda), pra que o dia conte no relatório.
        Apontamento::create([
            'sessao_trabalho_id'        => $this->criarSessao($maquina, Carbon::today()->setTime(6, 30))->id,
            'etapa_fluxo_id'            => $etapa->id,
            'cod_peca'                  => '7654321',
            'ordem_lote'                => '00002',
            'desc_peca'                 => 'Peça Hoje',
            'cod_produto'               => 'PROD-0002',
            'qtde_total'                => 50,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'              => Carbon::today()->setTime(7, 0),
            'setup_fim'                 => Carbon::today()->setTime(8, 0),
            'setup_duracao_segundos'    => 3600,
        ]);

        // Mas o turno vigente a partir de hoje já reflete o novo horário.
        $relatorioHoje = app(RelatorioProducaoService::class)
            ->relatorioMaquinasPorPeriodo(Carbon::today(), Carbon::today());
        $this->assertSame(50400, $relatorioHoje['maquinas'][0]['tempo_turno_segundos']); // 14h, turno novo
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
