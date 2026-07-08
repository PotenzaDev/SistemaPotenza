<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\FichaCabecote;
use App\Models\Operario;
use App\Models\Produto;
use App\Models\ProdutoPeca;
use App\Models\SessaoTrabalho;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApontamentoFichaSetupTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarOperarioComApontamento(array $atributosApontamento = []): array
    {
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create(['operario_id' => $operario->id]);
        $apontamento = Apontamento::factory()->create(array_merge(
            ['sessao_trabalho_id' => $sessao->id],
            $atributosApontamento
        ));

        return [$user, $apontamento];
    }

    public function test_retorna_ficha_de_setup_quando_peca_possui_ficha_cadastrada(): void
    {
        $produto = Produto::factory()->create(['cod_produto' => 'PROD-0001']);
        $peca    = ProdutoPeca::factory()->create(['produto_id' => $produto->id, 'numero' => 1234567]);
        $ficha   = FichaCabecote::factory()->create(['produto_peca_id' => $peca->id]);

        [$user, $apontamento] = $this->autenticarOperarioComApontamento([
            'cod_produto' => 'PROD-0001',
            'cod_peca'    => '1234567',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/apontamento/{$apontamento->id}/ficha-setup")
            ->assertOk()
            ->assertJsonPath('data.id', $ficha->id)
            ->assertJsonPath('data.produto_peca_id', $peca->id);
    }

    public function test_retorna_null_quando_peca_nao_possui_ficha_cadastrada(): void
    {
        $produto = Produto::factory()->create(['cod_produto' => 'PROD-0002']);
        ProdutoPeca::factory()->create(['produto_id' => $produto->id, 'numero' => 7654321]);

        [$user, $apontamento] = $this->autenticarOperarioComApontamento([
            'cod_produto' => 'PROD-0002',
            'cod_peca'    => '7654321',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/apontamento/{$apontamento->id}/ficha-setup")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_retorna_null_quando_produto_nao_foi_importado_localmente(): void
    {
        [$user, $apontamento] = $this->autenticarOperarioComApontamento([
            'cod_produto' => 'PROD-INEXISTENTE',
            'cod_peca'    => '1112223',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/apontamento/{$apontamento->id}/ficha-setup")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_retorna_null_quando_cod_peca_nao_e_numerico(): void
    {
        $produto = Produto::factory()->create(['cod_produto' => 'PROD-0003']);
        ProdutoPeca::factory()->create(['produto_id' => $produto->id, 'numero' => 1]);

        [$user, $apontamento] = $this->autenticarOperarioComApontamento([
            'cod_produto' => 'PROD-0003',
            'cod_peca'    => 'ABC1234',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/apontamento/{$apontamento->id}/ficha-setup")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_operario_de_outra_sessao_nao_pode_ver_ficha_setup(): void
    {
        [, $apontamento] = $this->autenticarOperarioComApontamento();

        $outroUser = User::factory()->operario()->create();
        Operario::factory()->create(['user_id' => $outroUser->id]);

        $this->actingAs($outroUser, 'sanctum')
            ->getJson("/api/apontamento/{$apontamento->id}/ficha-setup")
            ->assertForbidden();
    }
}
