<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApontamentoDoDiaSessaoCanceladaTest extends TestCase
{
    use RefreshDatabase;

    public function test_apontamento_de_sessao_cancelada_mantem_operario_e_maquina_no_listing(): void
    {
        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true, 'nome' => 'Máquina X']);
        $user     = User::factory()->operario()->create(['name' => 'Operário X']);
        $operario = Operario::factory()->create(['user_id' => $user->id]);

        $sessao = SessaoTrabalho::create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'inicio'      => now(),
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $apontamento = Apontamento::factory()->finalizado()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'setup_inicio'       => now(),
            'producao_fim'       => now(),
        ]);

        $sessao->delete(); // soft delete = sessão cancelada

        $repo   = app(ApontamentoRepositoryInterface::class);
        $lista  = $repo->apontamentosDoDia(['operario_id' => $operario->id]);
        $achado = $lista->firstWhere('id', $apontamento->id);

        $this->assertNotNull($achado, 'apontamento da sessão cancelada deveria continuar aparecendo na listagem');
        $this->assertNotNull($achado->sessaoTrabalho, 'sessaoTrabalho não deveria ser null mesmo soft-deleted');
        $this->assertSame('Operário X', $achado->sessaoTrabalho->operario->user->name);
        $this->assertSame('Máquina X', $achado->sessaoTrabalho->maquina->nome);
    }
}
