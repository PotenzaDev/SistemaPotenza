<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Broca;
use App\Models\FichaCabecote;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\ProdutoPeca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FichaCabecoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(Maquina $maquina, Operario $operario, Broca $broca): array
    {
        return [
            'maquina_id' => $maquina->id,
            'operario_id' => $operario->id,
            'data' => '2026-07-03',
            'top_esquerdo_mm' => 10.5,
            'top_direito_mm' => 12.5,
            'quantidade_pecas_vez' => 4,
            'velocidade_trabalho' => 55.5,
            'observacao' => 'Observação de teste',
            'posicoes_cabecote' => [
                [
                    'cabecote' => '1',
                    'sentido' => 'inferior',
                    'largura_mm' => 20,
                    'deslocamento_mm' => 5,
                    'altura_cabecote_mm' => 30,
                    'obs' => null,
                ],
            ],
            'posicoes_broca' => [
                [
                    'cabecote' => '1',
                    'sentido' => 'inferior',
                    'posicao' => '1',
                    'broca_id' => $broca->id,
                    'passante' => true,
                    'profundidade_mm' => null,
                    'agregado' => null,
                    'obs' => null,
                ],
            ],
        ];
    }

    public function test_funcionario_pode_criar_ficha_cabecote_com_posicoes(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $peca = ProdutoPeca::factory()->create();
        $maquina = Maquina::factory()->create();
        $operario = Operario::factory()->create();
        $broca = Broca::factory()->create();

        $payload = $this->payloadValido($maquina, $operario, $broca);

        $this->actingAs($funcionario, 'sanctum')
            ->postJson("/api/produto-pecas/{$peca->id}/fichas-cabecote", $payload)
            ->assertCreated()
            ->assertJsonPath('data.produto_peca_id', $peca->id)
            ->assertJsonCount(1, 'data.posicoes_cabecote')
            ->assertJsonCount(1, 'data.posicoes_broca');

        $this->assertDatabaseCount('fichas_cabecote', 1);
        $this->assertDatabaseCount('ficha_cabecote_posicoes', 1);
        $this->assertDatabaseCount('ficha_cabecote_brocas', 1);
    }

    public function test_store_falha_quando_broca_nao_passante_sem_profundidade(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $peca = ProdutoPeca::factory()->create();
        $maquina = Maquina::factory()->create();
        $operario = Operario::factory()->create();
        $broca = Broca::factory()->create();

        $payload = $this->payloadValido($maquina, $operario, $broca);
        $payload['posicoes_broca'][0]['passante'] = false;
        $payload['posicoes_broca'][0]['profundidade_mm'] = null;

        $this->actingAs($funcionario, 'sanctum')
            ->postJson("/api/produto-pecas/{$peca->id}/fichas-cabecote", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['posicoes_broca.0.profundidade_mm']);
    }

    public function test_store_permite_salvar_rascunho_com_tabelas_vazias(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $peca = ProdutoPeca::factory()->create();
        $maquina = Maquina::factory()->create();
        $operario = Operario::factory()->create();
        $broca = Broca::factory()->create();

        $payload = $this->payloadValido($maquina, $operario, $broca);
        $payload['posicoes_cabecote'] = [];
        $payload['posicoes_broca'] = [];

        $this->actingAs($funcionario, 'sanctum')
            ->postJson("/api/produto-pecas/{$peca->id}/fichas-cabecote", $payload)
            ->assertCreated()
            ->assertJsonPath('data.completa', false);

        $this->assertDatabaseCount('fichas_cabecote', 1);
    }

    public function test_store_permite_salvar_rascunho_so_com_identificacao_incompleta(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $peca = ProdutoPeca::factory()->create();

        $this->actingAs($funcionario, 'sanctum')
            ->postJson("/api/produto-pecas/{$peca->id}/fichas-cabecote", [])
            ->assertCreated()
            ->assertJsonPath('data.maquina_id', null)
            ->assertJsonPath('data.completa', false);

        $this->assertDatabaseCount('fichas_cabecote', 1);
    }

    public function test_update_completa_ficha_rascunho_e_marca_como_completa(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $peca = ProdutoPeca::factory()->create();
        $maquina = Maquina::factory()->create();
        $operario = Operario::factory()->create();
        $broca = Broca::factory()->create();

        $ficha = FichaCabecote::factory()->create([
            'produto_peca_id' => $peca->id,
            'maquina_id' => null,
            'operario_id' => null,
            'data' => null,
            'top_esquerdo_mm' => null,
            'top_direito_mm' => null,
            'quantidade_pecas_vez' => null,
            'velocidade_trabalho' => null,
        ]);

        $payload = $this->payloadValido($maquina, $operario, $broca);

        $this->actingAs($funcionario, 'sanctum')
            ->putJson("/api/fichas-cabecote/{$ficha->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.completa', true)
            ->assertJsonCount(1, 'data.posicoes_cabecote')
            ->assertJsonCount(1, 'data.posicoes_broca');
    }

    public function test_update_retorna_404_quando_ficha_nao_existe(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->putJson('/api/fichas-cabecote/999999', [])
            ->assertStatus(404);
    }

    public function test_funcionario_pode_listar_fichas_da_peca(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $peca = ProdutoPeca::factory()->create();
        FichaCabecote::factory()->count(2)->create(['produto_peca_id' => $peca->id]);

        $this->actingAs($funcionario, 'sanctum')
            ->getJson("/api/produto-pecas/{$peca->id}/fichas-cabecote")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_retorna_404_quando_peca_nao_existe(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/produto-pecas/999999/fichas-cabecote')
            ->assertStatus(404);
    }

    public function test_show_retorna_ficha_com_relacoes(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $peca = ProdutoPeca::factory()->create();
        $ficha = FichaCabecote::factory()->create(['produto_peca_id' => $peca->id]);

        $this->actingAs($funcionario, 'sanctum')
            ->getJson("/api/fichas-cabecote/{$ficha->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ficha->id)
            ->assertJsonPath('data.maquina.id', $ficha->maquina_id)
            ->assertJsonPath('data.operario.id', $ficha->operario_id);
    }

    public function test_show_retorna_404_quando_ficha_nao_existe(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/fichas-cabecote/999999')
            ->assertStatus(404);
    }

    public function test_pdf_de_ficha_existente_retorna_documento_pdf(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $peca = ProdutoPeca::factory()->create();
        $maquina = Maquina::factory()->create();
        $operario = Operario::factory()->create();
        $broca = Broca::factory()->create();

        $payload = $this->payloadValido($maquina, $operario, $broca);
        $ficha = FichaCabecote::factory()->create(array_merge(
            ['produto_peca_id' => $peca->id],
            collect($payload)->except(['posicoes_cabecote', 'posicoes_broca'])->all()
        ));

        $response = $this->actingAs($funcionario, 'sanctum')
            ->get("/api/fichas-cabecote/{$ficha->id}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_pdf_retorna_404_quando_ficha_nao_existe(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->get('/api/fichas-cabecote/999999/pdf')
            ->assertStatus(404);
    }

    public function test_blank_pdf_de_peca_existente_retorna_documento_pdf(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();
        $peca = ProdutoPeca::factory()->create();

        $response = $this->actingAs($funcionario, 'sanctum')
            ->get("/api/produto-pecas/{$peca->id}/ficha-cabecote-branco/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_blank_pdf_retorna_404_quando_peca_nao_existe(): void
    {
        $funcionario = User::factory()->funcionario(['produtos'])->create();

        $this->actingAs($funcionario, 'sanctum')
            ->get('/api/produto-pecas/999999/ficha-cabecote-branco/pdf')
            ->assertStatus(404);
    }
}
