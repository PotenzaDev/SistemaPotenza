<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdutoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_funcionario_com_rotina_liberada_pode_listar_produtos(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $produto = Produto::factory()->create();
        $produto->pecas()->create([
            'numero' => 1,
            'nome' => 'Semi-acabado teste',
            'sub_grupo' => 'SG1',
            'dimensao' => '100x200x18mm',
            'material' => 'MDF',
            'ordem' => 1,
        ]);
        Produto::factory()->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/produtos')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.pecas_count', fn ($count) => in_array($count, [0, 1], true));
    }

    public function test_funcionario_pode_ver_produto_com_semi_acabados(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $produto = Produto::factory()->create();
        $peca = $produto->pecas()->create([
            'numero' => 1,
            'nome' => 'Semi-acabado teste',
            'sub_grupo' => 'SG1',
            'dimensao' => '100x200x18mm',
            'material' => 'MDF',
            'ordem' => 1,
        ]);
        \App\Models\FichaCabecote::factory()->create(['produto_peca_id' => $peca->id]);

        $this->actingAs($funcionario, 'sanctum')
            ->getJson("/api/produtos/{$produto->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $produto->id)
            ->assertJsonCount(1, 'data.pecas')
            ->assertJsonPath('data.pecas.0.fichas_cabecote_count', 1);
    }

    public function test_show_retorna_404_quando_produto_nao_existe(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/produtos/999999')
            ->assertStatus(404);
    }

    public function test_admin_pode_desativar_produto(): void
    {
        $admin = User::factory()->admin()->create();
        $produto = Produto::factory()->create(['ativo' => true]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/produtos/{$produto->id}")
            ->assertOk()
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'ativo' => false]);
    }

    public function test_destroy_retorna_404_quando_produto_nao_existe(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/produtos/999999')
            ->assertStatus(404);
    }

    public function test_buscar_erp_retorna_422_quando_nome_e_sub_grupo_ausentes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/produtos/buscar-erp?empresa=FBM')
            ->assertStatus(422);
    }

    public function test_buscar_erp_retorna_422_quando_empresa_invalida(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/produtos/buscar-erp?empresa=XXX&nome=cadeira')
            ->assertStatus(422);
    }
}
