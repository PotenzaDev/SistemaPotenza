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
use App\Models\Turno;
use App\Models\User;
use App\Services\TimelineMaquinaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Diagnóstico: com o turno configurado com intervalo de almoço (12h-13h),
 * uma pausa real que engloba esse intervalo não deve gerar nenhum segmento
 * "pausa" dentro de 12h-13h — essa janela deve ficar fora do cálculo, como
 * já ocorre para produção/setup (ver TurnoCalculoService::janelasUteis()).
 */
class TimelineMaquinaIntervaloAlmocoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_pausa_que_engloba_o_intervalo_de_almoco_nao_conta_entre_12h_e_13h(): void
    {
        $segunda = Carbon::parse('2026-06-08 00:00:00');
        Carbon::setTestNow($segunda->copy()->setTime(15, 0));

        Turno::where('dia_semana', 1)->update([
            'intervalo_inicio' => '12:00:00',
            'intervalo_fim'    => '13:00:00',
        ]);

        $etapa   = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $motivo  = MotivoPausa::create(['nome' => 'Falta de Material', 'ativo' => true, 'is_sistema' => false]);

        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
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
            'desc_peca'          => 'Peça Teste',
            'cod_produto'        => 'PROD-0001',
            'qtde_total'         => 100,
            'status'             => Apontamento::STATUS_EM_PRODUCAO,
            'setup_inicio'       => $segunda->copy()->setTime(8, 0),
            'setup_fim'          => $segunda->copy()->setTime(8, 30),
            'producao_inicio'    => $segunda->copy()->setTime(8, 30),
            'producao_fim'       => null,
        ]);

        // Pausa real que engloba o intervalo de almoço inteiro: 11h às 14h.
        Pausa::create([
            'apontamento_id'   => $apontamento->id,
            'motivo_pausa_id'  => $motivo->id,
            'fase'             => 'producao',
            'inicio'           => $segunda->copy()->setTime(11, 0),
            'fim'              => $segunda->copy()->setTime(14, 0),
            'duracao_segundos' => 3 * 3600,
        ]);

        $timeline = app(TimelineMaquinaService::class)->timelineDoDia($segunda, $maquina->id);
        $segmentos = $timeline['maquinas'][0]['segmentos'];

        foreach ($segmentos as $segmento) {
            $inicio = Carbon::parse($segmento['inicio']);
            $fim    = Carbon::parse($segmento['fim']);

            if ($segmento['tipo'] === 'pausa') {
                $this->assertTrue(
                    $inicio->hour >= 13 || $fim->lessThanOrEqualTo($segunda->copy()->setTime(12, 0)),
                    "Segmento de pausa {$inicio->format('H:i:s')}-{$fim->format('H:i:s')} invade o intervalo de almoço (12h-13h)"
                );
            }
        }
    }
}
