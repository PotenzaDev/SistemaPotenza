<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\RegraMaquina;
use App\Models\SessaoTrabalho;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApontamentoSegundaPassagemLimiteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Apontamento} */
    private function prepararOrigemFinalizada(?RegraMaquina $regra, int $numeroPassagemOrigem = 1): array
    {
        $maquina = Maquina::factory()->create();

        if ($regra) {
            RegraMaquina::create(array_merge(['maquina_id' => $maquina->id], $regra->toArray()));
        }

        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $origem = Apontamento::factory()->finalizado()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $maquina->etapa_fluxo_id,
            'ordem_lote'         => '12345',
            'cod_peca'           => '1234567',
            'numero_passagem'    => $numeroPassagemOrigem,
        ]);

        return [$user, $origem];
    }

    public function test_bloqueia_segunda_passagem_quando_maquina_nao_permite(): void
    {
        [$user] = $this->prepararOrigemFinalizada(
            new RegraMaquina(['permite_multiplas_passagens' => false, 'possui_setup' => true, 'possui_producao' => true])
        );

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/segunda-passagem', [
                'cod_peca'   => '1234567',
                'ordem_lote' => '12345',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta máquina não permite mais de uma passagem por ficha.');
    }

    public function test_bloqueia_segunda_passagem_quando_excede_limite_configurado(): void
    {
        [$user] = $this->prepararOrigemFinalizada(
            new RegraMaquina(['permite_multiplas_passagens' => true, 'limite_passagens' => 2, 'possui_setup' => true, 'possui_producao' => true]),
            numeroPassagemOrigem: 2,
        );

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/segunda-passagem', [
                'cod_peca'   => '1234567',
                'ordem_lote' => '12345',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Limite de 2 passagens atingido nesta máquina.');
    }

    public function test_permite_segunda_passagem_dentro_do_limite_configurado(): void
    {
        [$user] = $this->prepararOrigemFinalizada(
            new RegraMaquina(['permite_multiplas_passagens' => true, 'limite_passagens' => 3, 'possui_setup' => true, 'possui_producao' => true]),
            numeroPassagemOrigem: 1,
        );

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/segunda-passagem', [
                'cod_peca'   => '1234567',
                'ordem_lote' => '12345',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('apontamentos', [
            'ordem_lote'      => '12345',
            'cod_peca'        => '1234567',
            'numero_passagem' => 2,
        ]);
    }

    public function test_permite_segunda_passagem_quando_sem_limite_configurado(): void
    {
        [$user] = $this->prepararOrigemFinalizada(
            new RegraMaquina(['permite_multiplas_passagens' => true, 'limite_passagens' => null, 'possui_setup' => true, 'possui_producao' => true]),
            numeroPassagemOrigem: 5,
        );

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/segunda-passagem', [
                'cod_peca'   => '1234567',
                'ordem_lote' => '12345',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('apontamentos', [
            'ordem_lote'      => '12345',
            'cod_peca'        => '1234567',
            'numero_passagem' => 6,
        ]);
    }

    public function test_permite_segunda_passagem_quando_maquina_nao_possui_regra_cadastrada(): void
    {
        [$user] = $this->prepararOrigemFinalizada(null);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/segunda-passagem', [
                'cod_peca'   => '1234567',
                'ordem_lote' => '12345',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('apontamentos', [
            'ordem_lote'      => '12345',
            'cod_peca'        => '1234567',
            'numero_passagem' => 2,
        ]);
    }
}
