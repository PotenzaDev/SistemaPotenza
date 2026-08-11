<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\FichaApontamento;
use App\Models\Maquina;
use App\Models\MotivoPausa;
use App\Models\Operario;
use App\Models\Pausa;
use App\Models\Produto;
use App\Models\ProdutoPeca;
use App\Models\SessaoTrabalho;
use App\Models\User;
use App\Services\RelatorioProducaoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioApontamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_relatorio_lista_segmentos_em_ordem_cronologica_com_maquina_usuario_e_motivo_da_pausa(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00'); // turno 08:00-17:00

        [$operario, $maquina, $sessao] = $this->criarSessao($segunda->copy()->setTime(6, 30));

        $apontamento = Apontamento::create([
            'sessao_trabalho_id'        => $sessao->id,
            'etapa_fluxo_id'            => $sessao->maquina->etapa_fluxo_id,
            'cod_peca'                  => '1234567',
            'ordem_lote'                => '00001',
            'desc_peca'                 => 'Peça Teste',
            'cod_produto'               => 'PROD-0001',
            'qtde_total'                => 100,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'              => $segunda->copy()->setTime(7, 0),
            'setup_fim'                 => $segunda->copy()->setTime(11, 0),
            'setup_duracao_segundos'    => 10800,
            'producao_inicio'           => $segunda->copy()->setTime(11, 0),
            'producao_fim'              => $segunda->copy()->setTime(14, 0),
            'producao_duracao_segundos' => 7200,
            'total_pausa_segundos'      => 3600,
        ]);

        $ajuste = MotivoPausa::create(['nome' => 'Ajuste de Lâmina', 'ativo' => true, 'is_sistema' => false]);
        $almoco = MotivoPausa::create(['nome' => 'Almoço', 'ativo' => true, 'is_sistema' => false]);

        Pausa::create([
            'apontamento_id'   => $apontamento->id,
            'motivo_pausa_id'  => $ajuste->id,
            'fase'             => 'setup',
            'inicio'           => $segunda->copy()->setTime(9, 0),
            'fim'              => $segunda->copy()->setTime(10, 0),
            'duracao_segundos' => 3600,
        ]);

        Pausa::create([
            'apontamento_id'   => $apontamento->id,
            'motivo_pausa_id'  => $almoco->id,
            'fase'             => 'producao',
            'inicio'           => $segunda->copy()->setTime(12, 0),
            'fim'              => $segunda->copy()->setTime(13, 0),
            'duracao_segundos' => 3600,
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioApontamentosPorPeriodo($segunda, $segunda);

        $this->assertCount(6, $relatorio);

        $this->assertSame(['setup', 'pausa', 'setup', 'producao', 'pausa', 'producao'], array_column($relatorio, 'tipo'));

        $horarios = array_map(
            fn (array $linha) => [substr($linha['inicio'], 11, 5), substr($linha['fim'], 11, 5)],
            $relatorio
        );
        $this->assertSame([
            ['07:00', '09:00'],
            ['09:00', '10:00'],
            ['10:00', '11:00'],
            ['11:00', '12:00'],
            ['12:00', '13:00'],
            ['13:00', '14:00'],
        ], $horarios);

        $this->assertNull($relatorio[0]['motivo_pausa']);
        $this->assertSame('Ajuste de Lâmina', $relatorio[1]['motivo_pausa']);
        $this->assertSame('Almoço', $relatorio[4]['motivo_pausa']);

        $this->assertSame($maquina->nome_com_codigo, $relatorio[0]['maquina']);
        $this->assertSame($operario->user->name, $relatorio[0]['usuario']);
        $this->assertSame(7200, $relatorio[0]['duracao_segundos']); // 07:00-09:00
    }

    public function test_relatorio_inclui_ids_setor_qtd_produzida_e_resumo_de_fichas_com_nomes(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');

        [$operario, $maquina, $sessao] = $this->criarSessao($segunda->copy()->setTime(7, 0));

        $produto = Produto::factory()->create(['cod_produto' => 'PROD-0001', 'nome' => 'Guarda-Roupa Duna']);
        $peca    = ProdutoPeca::factory()->create(['produto_id' => $produto->id, 'numero' => 12, 'nome' => 'Porta Lateral']);

        $apontamento = $this->criarApontamentoSimples($sessao, $segunda->copy()->setTime(8, 0), $segunda->copy()->setTime(9, 0));
        $apontamento->update(['cod_produto' => 'PROD-0001']);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => (string) $peca->numero,
            'cod_produto'    => 'PROD-0001',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'qtd_produzida'  => 50,
            'bipada_at'      => $segunda->copy()->setTime(9, 0),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '9999', // sem ProdutoPeca cadastrada com esse número
            'cod_produto'    => 'PROD-0001',
            'pilha'          => 2,
            'qtd_peca'       => 30,
            'qtd_produzida'  => 20,
            'bipada_at'      => $segunda->copy()->setTime(9, 5),
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioApontamentosPorPeriodo($segunda, $segunda);

        $this->assertCount(1, $relatorio);
        $linha = $relatorio[0];

        $this->assertSame($maquina->id, $linha['maquina_id']);
        $this->assertSame($operario->user_id, $linha['user_id']);
        $this->assertSame($maquina->etapaFluxo->nome, $linha['setor']);
        $this->assertSame(70, $linha['qtd_total_produzida']); // 50 + 20

        $this->assertSame(
            'Pilha 1: Porta Lateral (Guarda-Roupa Duna) 50/50; Pilha 2: 9999 (Guarda-Roupa Duna) 20/30',
            $linha['fichas']
        );
    }

    public function test_pausa_fim_de_turno_nao_gera_linha_mas_divide_o_segmento_entre_os_dias(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00'); // turno 08:00-17:00
        $terca   = $segunda->copy()->addDay();

        [, $maquina, $sessao] = $this->criarSessao($segunda->copy()->setTime(7, 30));

        $apontamento = Apontamento::create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $sessao->maquina->etapa_fluxo_id,
            'cod_peca'           => '7654321',
            'ordem_lote'         => '00002',
            'desc_peca'          => 'Peça Multi-dia',
            'cod_produto'        => 'PROD-0002',
            'qtde_total'         => 50,
            'status'             => Apontamento::STATUS_AGUARDANDO_PRODUCAO,
            'setup_inicio'       => $segunda->copy()->setTime(16, 0),
            'setup_fim'          => $terca->copy()->setTime(9, 0),
        ]);

        $fimTurno = MotivoPausa::where('nome', 'Fim de Turno')->where('is_sistema', true)->firstOrFail();

        Pausa::create([
            'apontamento_id'  => $apontamento->id,
            'motivo_pausa_id' => $fimTurno->id,
            'fase'            => 'setup',
            'inicio'          => $segunda->copy()->setTime(17, 0),
            'fim'             => $terca->copy()->setTime(8, 0),
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioApontamentosPorPeriodo($segunda, $terca);

        $this->assertCount(2, $relatorio);
        $this->assertSame(['setup', 'setup'], array_column($relatorio, 'tipo'));
        $this->assertFalse(collect($relatorio)->contains('motivo_pausa', 'Fim de Turno'));
        $this->assertSame($segunda->toDateString(), $relatorio[0]['data']);
        $this->assertSame($terca->toDateString(), $relatorio[1]['data']);
    }

    public function test_filtra_por_maquina_grupo_e_operario(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');

        [$operarioA, $maquinaA, $sessaoA] = $this->criarSessao($segunda->copy()->setTime(7, 0));
        [, $maquinaB, $sessaoB] = $this->criarSessao($segunda->copy()->setTime(7, 0));

        $this->criarApontamentoSimples($sessaoA, $segunda->copy()->setTime(8, 0), $segunda->copy()->setTime(9, 0));
        $this->criarApontamentoSimples($sessaoB, $segunda->copy()->setTime(8, 0), $segunda->copy()->setTime(9, 0));

        $service = app(RelatorioProducaoService::class);

        $porMaquina = $service->relatorioApontamentosPorPeriodo($segunda, $segunda, maquinaId: $maquinaA->id);
        $this->assertCount(1, $porMaquina);
        $this->assertSame($maquinaA->id, $porMaquina[0]['maquina_id']);

        $porGrupo = $service->relatorioApontamentosPorPeriodo($segunda, $segunda, grupoId: $maquinaB->etapa_fluxo_id);
        $this->assertTrue(collect($porGrupo)->contains('maquina_id', $maquinaB->id));
        $this->assertFalse(collect($porGrupo)->contains('maquina_id', $maquinaA->id));

        $porOperario = $service->relatorioApontamentosPorPeriodo($segunda, $segunda, operarioId: $operarioA->id);
        $this->assertCount(1, $porOperario);
        $this->assertSame($operarioA->id, $porOperario[0]['operario_id']);
    }

    public function test_gestor_pode_acessar_relatorio_e_operario_recebe_403(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');

        $gestor   = User::factory()->gestor()->create();
        $operario = User::factory()->operario()->create();

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/admin/relatorio-apontamentos?' . http_build_query([
                'data_inicio' => $segunda->toDateString(),
                'data_fim'    => $segunda->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($operario, 'sanctum')
            ->getJson('/api/admin/relatorio-apontamentos')
            ->assertForbidden();
    }

    public function test_export_retorna_planilha_xlsx(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');

        [, , $sessao] = $this->criarSessao($segunda->copy()->setTime(7, 0));
        $this->criarApontamentoSimples($sessao, $segunda->copy()->setTime(8, 0), $segunda->copy()->setTime(9, 0));

        $gestor = User::factory()->gestor()->create();

        $response = $this->actingAs($gestor, 'sanctum')
            ->get('/api/admin/relatorio-apontamentos/export?' . http_build_query([
                'data_inicio' => $segunda->toDateString(),
                'data_fim'    => $segunda->toDateString(),
            ]));

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
    }

    public function test_filtros_retorna_grupos_maquinas_e_operarios(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->criarSessao(Carbon::parse('2026-06-08 07:00:00'));

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/admin/relatorio-apontamentos/filtros')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['grupos', 'maquinas', 'operarios']]);
    }

    public function test_data_fim_anterior_a_data_inicio_retorna_erro_de_validacao(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/admin/relatorio-apontamentos?' . http_build_query([
                'data_inicio' => '2026-06-10',
                'data_fim'    => '2026-06-08',
            ]))
            ->assertStatus(422);
    }

    /** @return array{0: Operario, 1: Maquina, 2: SessaoTrabalho} */
    private function criarSessao(Carbon $inicio): array
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);

        $sessao = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'inicio'      => $inicio,
            'fim'         => null,
        ]);

        return [$operario, $maquina, $sessao];
    }

    private function criarApontamentoSimples(SessaoTrabalho $sessao, Carbon $setupInicio, Carbon $setupFim): Apontamento
    {
        return Apontamento::create([
            'sessao_trabalho_id'     => $sessao->id,
            'etapa_fluxo_id'         => $sessao->maquina->etapa_fluxo_id,
            'cod_peca'               => fake()->numerify('#######'),
            'ordem_lote'             => fake()->numerify('#####'),
            'desc_peca'              => 'Peça Teste',
            'cod_produto'            => 'PROD-' . fake()->numerify('####'),
            'qtde_total'             => 10,
            'status'                 => Apontamento::STATUS_AGUARDANDO_PRODUCAO,
            'setup_inicio'           => $setupInicio,
            'setup_fim'              => $setupFim,
            'setup_duracao_segundos' => $setupInicio->diffInSeconds($setupFim),
        ]);
    }
}
