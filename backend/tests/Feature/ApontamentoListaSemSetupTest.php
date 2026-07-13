<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use App\Services\ApontamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApontamentoListaSemSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_apontamento_finalizado_sem_setup_inicio_aparece_na_lista_do_dia(): void
    {
        $hoje = Carbon::parse('2026-07-13 00:00:00');

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);

        $sessao = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'inicio'      => $hoje->copy()->setTime(8, 0),
            'fim'         => null,
        ]);

        // Máquina com possui_setup=false: setup_inicio nunca é gravado.
        Apontamento::create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '1234567',
            'ordem_lote'         => '00001',
            'desc_peca'          => 'Peça Sem Setup',
            'cod_produto'        => 'PROD-0001',
            'qtde_total'         => 50,
            'status'             => Apontamento::STATUS_FINALIZADO,
            'setup_inicio'       => null,
            'setup_fim'          => null,
            'producao_inicio'    => $hoje->copy()->setTime(9, 0),
            'producao_fim'       => $hoje->copy()->setTime(10, 0),
        ]);

        $service   = app(ApontamentoService::class);
        $resultado = $service->listarApontamentos([
            'data_inicio' => $hoje->toDateString(),
            'data_fim'    => $hoje->toDateString(),
        ]);

        $this->assertCount(1, $resultado['apontamentos'], 'apontamento sem setup_inicio deve aparecer na lista do dia');
    }
}
