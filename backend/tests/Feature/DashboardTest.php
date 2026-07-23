<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_maquina_ativa_sem_nenhuma_atividade_hoje_nao_aparece(): void
    {
        Carbon::setTestNow('2026-06-08 10:00:00');

        $etapa = EtapaFluxo::factory()->create(['ativa' => true]);
        Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $resumo = app(DashboardService::class)->resumo();

        $this->assertSame([], $resumo['maquinas']);
    }

    public function test_maquina_com_sessao_aberta_agora_aparece_mesmo_sem_apontamento(): void
    {
        Carbon::setTestNow('2026-06-08 10:00:00');

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $this->criarSessao($maquina, Carbon::now()->setTime(7, 30));

        $resumo = app(DashboardService::class)->resumo();

        $this->assertCount(1, $resumo['maquinas']);
        $this->assertSame($maquina->id, $resumo['maquinas'][0]['id']);
        $this->assertSame('livre', $resumo['maquinas'][0]['status']);
    }

    public function test_maquina_que_trabalhou_e_ja_fechou_a_sessao_ainda_aparece_como_livre(): void
    {
        Carbon::setTestNow('2026-06-08 12:00:00');

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $sessao = $this->criarSessao($maquina, Carbon::today()->setTime(7, 30), Carbon::today()->setTime(9, 0));

        Apontamento::create([
            'sessao_trabalho_id'     => $sessao->id,
            'etapa_fluxo_id'         => $etapa->id,
            'cod_peca'               => '1234567',
            'ordem_lote'             => '00001',
            'desc_peca'              => 'Peça Teste',
            'cod_produto'            => 'PROD-0001',
            'qtde_total'             => 10,
            'status'                 => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'           => Carbon::today()->setTime(8, 0),
            'setup_fim'              => Carbon::today()->setTime(8, 30),
            'setup_duracao_segundos' => 1800,
        ]);

        $resumo = app(DashboardService::class)->resumo();

        $this->assertCount(1, $resumo['maquinas']);
        $this->assertSame($maquina->id, $resumo['maquinas'][0]['id']);
        $this->assertSame('livre', $resumo['maquinas'][0]['status']);
    }

    public function test_maquina_com_atividade_ontem_mas_nao_hoje_nao_aparece(): void
    {
        Carbon::setTestNow('2026-06-09 10:00:00');

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);

        $ontem  = Carbon::yesterday();
        $sessao = $this->criarSessao($maquina, $ontem->copy()->setTime(7, 30), $ontem->copy()->setTime(9, 0));

        Apontamento::create([
            'sessao_trabalho_id'     => $sessao->id,
            'etapa_fluxo_id'         => $etapa->id,
            'cod_peca'               => '1234567',
            'ordem_lote'             => '00001',
            'desc_peca'              => 'Peça Ontem',
            'cod_produto'            => 'PROD-0001',
            'qtde_total'             => 10,
            'status'                 => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'           => $ontem->copy()->setTime(8, 0),
            'setup_fim'              => $ontem->copy()->setTime(8, 30),
            'setup_duracao_segundos' => 1800,
        ]);

        $resumo = app(DashboardService::class)->resumo();

        $this->assertSame([], $resumo['maquinas']);
    }

    private function criarSessao(Maquina $maquina, Carbon $inicio, ?Carbon $fim = null): SessaoTrabalho
    {
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);

        return SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'inicio'      => $inicio,
            'fim'         => $fim,
        ]);
    }
}
