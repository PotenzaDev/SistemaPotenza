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
use App\Services\RelatorioProducaoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioTurnoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_relatorio_calcula_trabalhado_pausa_e_ocioso_dentro_de_um_turno(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00'); // segunda-feira, turno 08:00-17:00

        [$operario, , $sessao] = $this->criarSessao($segunda->copy()->setTime(7, 30));

        $apontamento = Apontamento::create([
            'sessao_trabalho_id'        => $sessao->id,
            'etapa_fluxo_id'            => $sessao->maquina->etapa_fluxo_id,
            'cod_peca'                  => '1234567',
            'ordem_lote'                => '00001',
            'desc_peca'                 => 'Peça Teste',
            'cod_produto'               => 'PROD-0001',
            'qtde_total'                => 100,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'              => $segunda->copy()->setTime(8, 0),
            'setup_fim'                 => $segunda->copy()->setTime(10, 0),
            'setup_duracao_segundos'    => 5400,
            'producao_inicio'           => $segunda->copy()->setTime(10, 0),
            'producao_fim'              => $segunda->copy()->setTime(14, 0),
            'producao_duracao_segundos' => 14400,
            'total_pausa_segundos'      => 1800,
        ]);

        $motivo = MotivoPausa::create(['nome' => 'Troca de Ferramenta', 'ativo' => true, 'is_sistema' => false]);

        Pausa::create([
            'apontamento_id'   => $apontamento->id,
            'motivo_pausa_id'  => $motivo->id,
            'fase'             => 'setup',
            'inicio'           => $segunda->copy()->setTime(9, 0),
            'fim'              => $segunda->copy()->setTime(9, 30),
            'duracao_segundos' => 1800,
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioPorDia($segunda, $operario->id);

        $this->assertCount(1, $relatorio);
        $linha = $relatorio[0];

        $this->assertSame(32400, $linha['tempo_turno_segundos']);
        $this->assertSame(19800, $linha['tempo_trabalhado_segundos']);
        $this->assertSame(1800, $linha['tempo_pausa_segundos']);
        $this->assertSame(['Troca de Ferramenta' => 1800], $linha['pausas_por_motivo']);
        $this->assertSame(10800, $linha['tempo_ocioso_segundos']);
    }

    public function test_apontamento_que_atravessa_a_virada_do_turno_e_dividido_entre_os_dois_dias(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00'); // turno 08:00-17:00
        $terca   = $segunda->copy()->addDay();           // turno 08:00-17:00

        [$operario, , $sessao] = $this->criarSessao($segunda->copy()->setTime(7, 30));

        $apontamento = Apontamento::create([
            'sessao_trabalho_id'     => $sessao->id,
            'etapa_fluxo_id'         => $sessao->maquina->etapa_fluxo_id,
            'cod_peca'               => '7654321',
            'ordem_lote'             => '00002',
            'desc_peca'              => 'Peça Multi-dia',
            'cod_produto'            => 'PROD-0002',
            'qtde_total'             => 50,
            'status'                 => Apontamento::STATUS_AGUARDANDO_PRODUCAO,
            'setup_inicio'           => $segunda->copy()->setTime(16, 0),
            'setup_fim'              => $terca->copy()->setTime(9, 0),
            'setup_duracao_segundos' => 7200,
        ]);

        $fimTurno = MotivoPausa::where('is_sistema', true)->firstOrFail();

        Pausa::create([
            'apontamento_id'   => $apontamento->id,
            'motivo_pausa_id'  => $fimTurno->id,
            'fase'             => 'setup',
            'inicio'           => $segunda->copy()->setTime(17, 0),
            'fim'              => $terca->copy()->setTime(8, 0),
            'duracao_segundos' => 54000,
        ]);

        $service = app(RelatorioProducaoService::class);

        $relatorioSegunda = $service->relatorioPorDia($segunda, $operario->id);
        $relatorioTerca   = $service->relatorioPorDia($terca, $operario->id);

        $this->assertCount(1, $relatorioSegunda);
        $this->assertSame(3600, $relatorioSegunda[0]['tempo_trabalhado_segundos']);
        $this->assertSame(0, $relatorioSegunda[0]['tempo_pausa_segundos']);
        $this->assertSame(28800, $relatorioSegunda[0]['tempo_ocioso_segundos']);

        $this->assertCount(1, $relatorioTerca);
        $this->assertSame(3600, $relatorioTerca[0]['tempo_trabalhado_segundos']);
        $this->assertSame(0, $relatorioTerca[0]['tempo_pausa_segundos']);
        $this->assertSame(28800, $relatorioTerca[0]['tempo_ocioso_segundos']);

        // O total bruto entre os dois dias bate com a duração líquida do setup.
        $this->assertSame(
            $apontamento->setup_duracao_segundos,
            $relatorioSegunda[0]['tempo_trabalhado_segundos'] + $relatorioTerca[0]['tempo_trabalhado_segundos']
        );
    }

    public function test_sessao_ativa_sem_apontamento_conta_como_tempo_ocioso(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');

        [$operario] = $this->criarSessao($segunda->copy()->setTime(8, 0));

        $relatorio = app(RelatorioProducaoService::class)->relatorioPorDia($segunda, $operario->id);

        $this->assertCount(1, $relatorio);
        $this->assertSame(32400, $relatorio[0]['tempo_turno_segundos']);
        $this->assertSame(0, $relatorio[0]['tempo_trabalhado_segundos']);
        $this->assertSame(0, $relatorio[0]['tempo_pausa_segundos']);
        $this->assertSame(32400, $relatorio[0]['tempo_ocioso_segundos']);
    }

    public function test_pausa_fim_de_turno_nao_conta_como_pausa_e_vai_para_ocioso(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00'); // segunda-feira, turno 08:00-17:00

        [$operario, , $sessao] = $this->criarSessao($segunda->copy()->setTime(7, 30));

        $apontamento = Apontamento::create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $sessao->maquina->etapa_fluxo_id,
            'cod_peca'           => '1112223',
            'ordem_lote'         => '00003',
            'desc_peca'          => 'Peça Fim de Turno',
            'cod_produto'        => 'PROD-0003',
            'qtde_total'         => 10,
            'status'             => Apontamento::STATUS_EM_PAUSA_PRODUCAO,
            'producao_inicio'    => $segunda->copy()->setTime(10, 0),
        ]);

        $fimTurno = MotivoPausa::where('nome', 'Fim de Turno')->where('is_sistema', true)->firstOrFail();

        Pausa::create([
            'apontamento_id'  => $apontamento->id,
            'motivo_pausa_id' => $fimTurno->id,
            'fase'            => 'producao',
            'inicio'          => $segunda->copy()->setTime(14, 0),
        ]);

        Carbon::setTestNow($segunda->copy()->setTime(18, 0));

        $relatorio = app(RelatorioProducaoService::class)->relatorioPorDia($segunda, $operario->id);

        $this->assertCount(1, $relatorio);
        $linha = $relatorio[0];

        $this->assertSame(32400, $linha['tempo_turno_segundos']);
        $this->assertSame(14400, $linha['tempo_trabalhado_segundos']);
        $this->assertSame(0, $linha['tempo_pausa_segundos']);
        $this->assertSame([], $linha['pausas_por_motivo']);
        $this->assertSame(18000, $linha['tempo_ocioso_segundos']);
    }

    public function test_intervalo_de_almoco_nao_conta_como_tempo_util(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00'); // segunda-feira, turno 08:00-17:00

        \App\Models\Turno::where('dia_semana', 1)->update([
            'intervalo_inicio' => '12:00:00',
            'intervalo_fim'    => '13:00:00',
        ]);

        [$operario, , $sessao] = $this->criarSessao($segunda->copy()->setTime(7, 30));

        Apontamento::create([
            'sessao_trabalho_id'        => $sessao->id,
            'etapa_fluxo_id'            => $sessao->maquina->etapa_fluxo_id,
            'cod_peca'                  => '2223334',
            'ordem_lote'                => '00004',
            'desc_peca'                 => 'Peça Almoço',
            'cod_produto'               => 'PROD-0004',
            'qtde_total'                => 10,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'producao_inicio'           => $segunda->copy()->setTime(10, 0),
            'producao_fim'              => $segunda->copy()->setTime(15, 0),
            'producao_duracao_segundos' => 18000,
        ]);

        $relatorio = app(RelatorioProducaoService::class)->relatorioPorDia($segunda, $operario->id);

        $this->assertCount(1, $relatorio);
        $linha = $relatorio[0];

        // Turno (8h) menos 1h de almoço = 28800s.
        $this->assertSame(28800, $linha['tempo_turno_segundos']);
        // Apontamento das 10h às 15h (5h), menos a 1h de almoço dentro desse intervalo = 4h trabalhadas.
        $this->assertSame(14400, $linha['tempo_trabalhado_segundos']);
        $this->assertSame(0, $linha['tempo_pausa_segundos']);
        $this->assertSame(14400, $linha['tempo_ocioso_segundos']);
    }

    public function test_abrir_turno_nao_reabre_sessao_finalizada_manualmente_no_mesmo_dia(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');

        [, , $sessao] = $this->criarSessao($segunda->copy()->setTime(8, 0));

        $sessao->update([
            'status'    => SessaoTrabalho::STATUS_INTERROMPIDA_TURNO,
            'fim'       => $segunda->copy()->setTime(14, 0),
            'fim_turno' => true,
        ]);

        Carbon::setTestNow($segunda->copy()->setTime(14, 5));

        $this->artisan('apontamento:abrir-turno');

        $this->assertSame(SessaoTrabalho::STATUS_INTERROMPIDA_TURNO, $sessao->fresh()->status);
    }

    public function test_abrir_turno_reabre_sessao_interrompida_em_dia_anterior(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');
        $terca   = $segunda->copy()->addDay();

        [, , $sessao] = $this->criarSessao($segunda->copy()->setTime(8, 0));

        $sessao->update([
            'status'    => SessaoTrabalho::STATUS_INTERROMPIDA_TURNO,
            'fim'       => $segunda->copy()->setTime(17, 0),
            'fim_turno' => true,
        ]);

        Carbon::setTestNow($terca->copy()->setTime(8, 5));

        $this->artisan('apontamento:abrir-turno');

        $fresh = $sessao->fresh();
        $this->assertSame(SessaoTrabalho::STATUS_ATIVA, $fresh->status);
        $this->assertNull($fresh->fim);
    }

    /** @return array{0: Operario, 1: Maquina, 2: SessaoTrabalho} */
    private function criarSessao(Carbon $inicio): array
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'inicio'      => $inicio,
            'fim'         => null,
        ]);

        return [$operario, $maquina, $sessao];
    }
}
