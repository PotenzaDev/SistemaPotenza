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
use App\Services\ApontamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListarApontamentosPorDiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_conta_apenas_pilhas_finalizadas_no_dia_filtrado_em_apontamento_de_dois_dias(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');
        $terca   = $segunda->copy()->addDay();

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);

        $sessao = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'inicio'      => $segunda->copy()->setTime(7, 30),
            'fim'         => null,
        ]);

        $apontamento = Apontamento::create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '1234567',
            'ordem_lote'         => '00001',
            'desc_peca'          => 'Peça Dois Dias',
            'cod_produto'        => 'PROD-0001',
            'qtde_total'         => 80,
            'status'             => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'       => $segunda->copy()->setTime(8, 0),
            'setup_fim'          => $segunda->copy()->setTime(9, 0),
            'producao_inicio'    => $segunda->copy()->setTime(9, 0),
            'producao_fim'       => $terca->copy()->setTime(12, 0),
        ]);

        // Pilha 1: bipada e finalizada na segunda.
        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '1234567',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'qtd_produzida'  => 50,
            'bipada_at'      => $segunda->copy()->setTime(9, 0),
            'fim_producao'   => $segunda->copy()->setTime(17, 0),
        ]);

        // Pilha 2: bipada e finalizada na terça (mesmo apontamento, virou o dia).
        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '1234567',
            'pilha'          => 2,
            'qtd_peca'       => 30,
            'qtd_produzida'  => 30,
            'bipada_at'      => $terca->copy()->setTime(8, 0),
            'fim_producao'   => $terca->copy()->setTime(12, 0),
        ]);

        $service = app(ApontamentoService::class);

        $ambosOsDias = $service->listarApontamentos([
            'data_inicio' => $segunda->toDateString(),
            'data_fim'    => $terca->toDateString(),
        ]);

        $this->assertCount(1, $ambosOsDias['apontamentos']);
        $this->assertSame(2, $ambosOsDias['apontamentos'][0]['qtd_pilhas']);
        $this->assertSame(80, $ambosOsDias['apontamentos'][0]['qtd_pecas']);
        $this->assertSame(2, $ambosOsDias['totais']['qtd_pilhas']);
        $this->assertSame(80, $ambosOsDias['totais']['qtd_pecas']);

        $somenteSegunda = $service->listarApontamentos([
            'data_inicio' => $segunda->toDateString(),
            'data_fim'    => $segunda->toDateString(),
        ]);

        $this->assertCount(1, $somenteSegunda['apontamentos'], 'apontamento deve aparecer mesmo só contando a pilha de segunda');
        $this->assertSame(1, $somenteSegunda['apontamentos'][0]['qtd_pilhas']);
        $this->assertSame(50, $somenteSegunda['apontamentos'][0]['qtd_pecas']);

        $somenteTerca = $service->listarApontamentos([
            'data_inicio' => $terca->toDateString(),
            'data_fim'    => $terca->toDateString(),
        ]);

        $this->assertCount(1, $somenteTerca['apontamentos'], 'apontamento deve aparecer mesmo só contando a pilha de terça');
        $this->assertSame(1, $somenteTerca['apontamentos'][0]['qtd_pilhas']);
        $this->assertSame(30, $somenteTerca['apontamentos'][0]['qtd_pecas']);
    }
}
