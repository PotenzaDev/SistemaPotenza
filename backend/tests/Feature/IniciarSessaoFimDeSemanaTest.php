<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IniciarSessaoFimDeSemanaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_iniciar_no_fim_de_semana_sem_turno_informado_e_rejeitado(): void
    {
        [$user, $maquina] = $this->prepararSabado();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Informe o horário de início e fim para trabalhar no fim de semana.');
    }

    public function test_iniciar_no_fim_de_semana_so_com_inicio_e_rejeitado(): void
    {
        [$user, $maquina] = $this->prepararSabado();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', [
                'maquina_id'             => $maquina->id,
                'turno_informado_inicio' => '08:00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Informe o horário de início e fim para trabalhar no fim de semana.');
    }

    public function test_iniciar_no_fim_de_semana_com_fim_antes_do_inicio_e_rejeitado(): void
    {
        [$user, $maquina] = $this->prepararSabado();

        // Barrado pela regra `after:turno_informado_inicio` do FormRequest,
        // antes mesmo de chegar à validação de negócio no service.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', [
                'maquina_id'             => $maquina->id,
                'turno_informado_inicio' => '14:00',
                'turno_informado_fim'    => '10:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turno_informado_fim']);
    }

    public function test_iniciar_no_fim_de_semana_com_inicio_e_fim_informados_cria_sessao(): void
    {
        [$user, $maquina] = $this->prepararSabado();

        $resposta = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', [
                'maquina_id'             => $maquina->id,
                'turno_informado_inicio' => '08:00',
                'turno_informado_fim'    => '14:00',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $sessao = SessaoTrabalho::findOrFail($resposta->json('data.id'));

        $this->assertSame('08:00:00', $sessao->turno_informado_inicio);
        $this->assertSame('14:00:00', $sessao->turno_informado_fim);
    }

    public function test_retomar_sessao_pausada_no_fim_de_semana_nao_exige_turno_informado(): void
    {
        [$user, $maquina] = $this->prepararSabado();

        $iniciar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', [
                'maquina_id'             => $maquina->id,
                'turno_informado_inicio' => '08:00',
                'turno_informado_fim'    => '14:00',
            ])
            ->assertCreated();

        $sessaoId = $iniciar->json('data.id');

        SessaoTrabalho::find($sessaoId)->update([
            'status' => SessaoTrabalho::STATUS_PAUSADA,
            'fim'    => Carbon::now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', [
                'maquina_id'        => $maquina->id,
                'sessao_pausada_id' => $sessaoId,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    /** @return array{0: User, 1: Maquina} */
    private function prepararSabado(): array
    {
        $sabado = Carbon::parse('2026-06-13 08:00:00'); // sábado — seeder não cadastra turno
        Carbon::setTestNow($sabado);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        Operario::factory()->create(['user_id' => $user->id]);

        return [$user, $maquina];
    }
}
