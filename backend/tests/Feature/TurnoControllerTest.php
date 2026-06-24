<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_pode_listar_turnos_dos_7_dias_da_semana(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/turnos')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(7, 'data');

        $dias = collect($response->json('data'))->pluck('dia_semana')->all();
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $dias);

        // Sábado (6) e domingo (7) não têm turno cadastrado pelo seeder.
        $this->assertFalse($response->json('data.5.ativo'));
        $this->assertFalse($response->json('data.6.ativo'));
    }

    public function test_admin_pode_atualizar_turno_existente(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '07:00',
                'hora_fim'                        => '16:00',
                'tolerancia_finalizacao_minutos'  => 15,
                'ativo'                           => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hora_inicio', '07:00:00')
            ->assertJsonPath('data.hora_fim', '16:00:00')
            ->assertJsonPath('data.tolerancia_finalizacao_minutos', 15);

        $this->assertDatabaseHas('turnos', [
            'dia_semana'                      => 1,
            'hora_inicio'                     => '07:00:00',
            'hora_fim'                        => '16:00:00',
            'tolerancia_finalizacao_minutos'  => 15,
        ]);
    }

    public function test_admin_pode_criar_turno_para_dia_sem_turno_cadastrado(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertNull(Turno::where('dia_semana', 6)->first());

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/6', [
                'hora_inicio'                     => '08:00',
                'hora_fim'                        => '12:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.dia_semana', 6)
            ->assertJsonPath('data.ativo', true);

        $this->assertDatabaseHas('turnos', [
            'dia_semana' => 6,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '12:00:00',
        ]);
    }

    public function test_atualizar_turno_rejeita_dia_semana_invalido(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/8', [
                'hora_inicio'                     => '08:00',
                'hora_fim'                        => '17:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertStatus(422);
    }

    public function test_atualizar_turno_rejeita_hora_fim_antes_da_hora_inicio(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '17:00',
                'hora_fim'                        => '08:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertStatus(422);
    }

    public function test_admin_pode_configurar_intervalo_de_almoco(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '07:00',
                'hora_fim'                        => '17:00',
                'intervalo_inicio'                => '12:00',
                'intervalo_fim'                   => '13:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.intervalo_inicio', '12:00:00')
            ->assertJsonPath('data.intervalo_fim', '13:00:00');

        $this->assertDatabaseHas('turnos', [
            'dia_semana'       => 1,
            'intervalo_inicio' => '12:00:00',
            'intervalo_fim'    => '13:00:00',
        ]);
    }

    public function test_atualizar_turno_rejeita_intervalo_fora_da_janela_do_turno(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '08:00',
                'hora_fim'                        => '17:00',
                'intervalo_inicio'                => '07:00',
                'intervalo_fim'                   => '08:30',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertStatus(422);
    }

    public function test_atualizar_turno_rejeita_intervalo_fim_antes_do_intervalo_inicio(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '08:00',
                'hora_fim'                        => '17:00',
                'intervalo_inicio'                => '13:00',
                'intervalo_fim'                   => '12:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertStatus(422);
    }

    public function test_atualizar_turno_rejeita_apenas_um_dos_campos_de_intervalo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '08:00',
                'hora_fim'                        => '17:00',
                'intervalo_inicio'                => '12:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertStatus(422);
    }

    public function test_editar_turno_em_dias_diferentes_preserva_a_versao_anterior(): void
    {
        $admin = User::factory()->admin()->create();

        Carbon::setTestNow('2026-06-10');
        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '07:00',
                'hora_fim'                        => '16:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertOk();

        Carbon::setTestNow('2026-06-12');
        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '09:00',
                'hora_fim'                        => '18:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertOk();

        // Versão original do seeder + 2 novas versões (uma por dia de edição).
        $this->assertSame(3, Turno::where('dia_semana', 1)->count());

        $this->assertSame('08:00:00', Turno::doDia(1, Carbon::parse('2026-06-01'))->hora_inicio);
        $this->assertSame('07:00:00', Turno::doDia(1, Carbon::parse('2026-06-11'))->hora_inicio);
        $this->assertSame('09:00:00', Turno::doDia(1, Carbon::parse('2026-06-12'))->hora_inicio);
    }

    public function test_editar_turno_duas_vezes_no_mesmo_dia_nao_cria_versao_extra(): void
    {
        $admin = User::factory()->admin()->create();

        Carbon::setTestNow('2026-06-10');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '07:00',
                'hora_fim'                        => '16:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '09:00',
                'hora_fim'                        => '18:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertOk();

        // Apenas 1 versão nova (a segunda edição atualizou a primeira no lugar).
        $this->assertSame(2, Turno::where('dia_semana', 1)->count());
        $this->assertSame('09:00:00', Turno::doDia(1, Carbon::parse('2026-06-10'))->hora_inicio);
    }

    public function test_operario_nao_pode_acessar_turnos(): void
    {
        $operario = User::factory()->operario()->create();

        $this->actingAs($operario, 'sanctum')
            ->getJson('/api/turnos')
            ->assertForbidden();

        $this->actingAs($operario, 'sanctum')
            ->putJson('/api/turnos/1', [
                'hora_inicio'                     => '08:00',
                'hora_fim'                        => '17:00',
                'tolerancia_finalizacao_minutos'  => 10,
                'ativo'                           => true,
            ])
            ->assertForbidden();
    }
}
