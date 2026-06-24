<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioSistemaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_listar_usuarios_do_sistema(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->funcionario()->create();
        User::factory()->operario()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/usuarios')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_pode_cadastrar_funcionario_com_modulos(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/usuarios', [
                'name'               => 'Func Teste',
                'email'              => 'func.teste@example.com',
                'password'           => 'senha123',
                'role'               => 'funcionario',
                'modulos_permitidos' => ['dashboard', 'relatorios'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'funcionario')
            ->assertJsonPath('data.modulos_permitidos', ['dashboard', 'relatorios']);

        $this->assertDatabaseHas('users', [
            'email' => 'func.teste@example.com',
            'role'  => 'funcionario',
        ]);
    }

    public function test_admin_pode_cadastrar_administrador_sem_modulos(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/usuarios', [
                'name'     => 'Admin Teste',
                'email'    => 'admin.teste@example.com',
                'password' => 'senha123',
                'role'     => 'admin',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.modulos_permitidos', null);
    }

    public function test_cadastro_rejeita_modulo_invalido(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/usuarios', [
                'name'               => 'Func Invalido',
                'email'              => 'func.invalido@example.com',
                'password'           => 'senha123',
                'role'               => 'funcionario',
                'modulos_permitidos' => ['modulo_inexistente'],
            ])
            ->assertStatus(422);
    }

    public function test_cadastro_rejeita_role_fora_do_permitido(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/usuarios', [
                'name'     => 'Operario Teste',
                'email'    => 'op.teste@example.com',
                'password' => 'senha123',
                'role'     => 'operario',
            ])
            ->assertStatus(422);
    }

    public function test_admin_pode_atualizar_modulos_de_funcionario(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = User::factory()->funcionario(['dashboard'])->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/usuarios/{$funcionario->id}", [
                'modulos_permitidos' => ['dashboard', 'maquinas'],
            ])
            ->assertOk()
            ->assertJsonPath('data.modulos_permitidos', ['dashboard', 'maquinas']);
    }

    public function test_admin_pode_remover_usuario_do_sistema(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = User::factory()->funcionario()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/usuarios/{$funcionario->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $funcionario->id]);
    }

    public function test_gestor_nao_pode_acessar_usuarios_do_sistema(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor, 'sanctum')
            ->getJson('/api/usuarios')
            ->assertForbidden();
    }

    public function test_funcionario_nao_pode_acessar_usuarios_do_sistema(): void
    {
        $funcionario = User::factory()->funcionario()->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/usuarios')
            ->assertForbidden();
    }

    public function test_operario_nao_pode_acessar_usuarios_do_sistema(): void
    {
        $operario = User::factory()->operario()->create();

        $this->actingAs($operario, 'sanctum')
            ->getJson('/api/usuarios')
            ->assertForbidden();
    }
}
