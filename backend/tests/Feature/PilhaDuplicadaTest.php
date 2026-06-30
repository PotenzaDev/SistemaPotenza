<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Apontamento;
use App\Models\EtapaFluxo;
use App\Models\FichaApontamento;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Models\User;
use App\Services\Lote\LoteServiceInterface;
use App\Services\Lote\MockLoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilhaDuplicadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloqueia_segunda_bipagem_quando_bridge_retorna_1_ficha(): void
    {
        $this->app->bind(LoteServiceInterface::class, MockLoteService::class);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
            ])
            ->assertStatus(422);
    }

    public function test_retorna_409_quando_bridge_permite_segunda_bipagem_sem_confirmar(): void
    {
        $this->app->instance(LoteServiceInterface::class, MockLoteService::com(2));

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('requiresConfirmation', true)
            ->assertJsonPath('passagensRealizadas', 1)
            ->assertJsonPath('passagensEsperadas', 2);
    }

    public function test_permite_segunda_bipagem_com_confirmar_true(): void
    {
        $this->app->instance(LoteServiceInterface::class, MockLoteService::com(2));

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
                'confirmar'  => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_bloqueia_segunda_bipagem_mesmo_com_confirmar_quando_bridge_bloqueou(): void
    {
        $this->app->bind(LoteServiceInterface::class, MockLoteService::class);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
                'confirmar'  => true,
            ])
            ->assertStatus(422);
    }

    /**
     * Garante que pilha de uma passagem anterior (apontamento diferente) conta
     * ao verificar o limite de bipagens — usa dois apontamentos distintos no
     * mesmo lote/etapa, como ocorre em segunda passagem real.
     */
    public function test_bloqueia_terceira_bipagem_quando_bridge_retorna_2_fichas(): void
    {
        $this->app->instance(LoteServiceInterface::class, MockLoteService::com(2));

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);

        // 1ª passagem (finalizada): já tem pilha 1 bipada
        $apontamento1 = Apontamento::factory()->finalizado()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
        ]);
        FichaApontamento::create([
            'apontamento_id' => $apontamento1->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now()->subHour(),
        ]);

        // 2ª passagem (em produção): também já tem pilha 1 bipada
        $apontamento2 = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id'    => $sessao->id,
            'etapa_fluxo_id'        => $etapa->id,
            'cod_peca'              => '4501940',
            'ordem_lote'            => '06854',
            'producao_inicio'       => now(),
            'numero_passagem'       => 2,
            'apontamento_origem_id' => $apontamento1->id,
        ]);
        FichaApontamento::create([
            'apontamento_id' => $apontamento2->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        // Tentativa de 3ª bipagem da pilha 1 deve ser bloqueada (limite = 2)
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento2->id}/bipar-ficha", [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
            ])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Variantes de produto (mesmo prefixo de 5 dígitos, últimos 2 diferentes)
    // -------------------------------------------------------------------------

    public function test_permite_bipar_variante_com_mesmo_prefixo(): void
    {
        $this->app->bind(LoteServiceInterface::class, MockLoteService::class);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        // Variante: prefixo "45019" igual, últimos 2 dígitos "62" diferentes
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501962',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 2,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_permite_bipar_variante_com_mesma_pilha_do_produto_original(): void
    {
        $this->app->bind(LoteServiceInterface::class, MockLoteService::class);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        // Produto original: pilha 1
        FichaApontamento::create([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => '4501940',
            'pilha'          => 1,
            'qtd_peca'       => 50,
            'bipada_at'      => now(),
        ]);

        // Variante com a mesma pilha 1 deve ser permitida (cod_peca diferente)
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501962',
                'ordem_lote' => '06854',
                'qtd_peca'   => 48,
                'pilha'      => 1,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_bloqueia_produto_com_prefixo_diferente(): void
    {
        $this->app->bind(LoteServiceInterface::class, MockLoteService::class);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        // Produto com prefixo totalmente diferente: deve ser bloqueado
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '5001962',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
            ])
            ->assertStatus(422);
    }

    public function test_soma_quantidade_das_variantes_no_total_bipado(): void
    {
        $this->app->bind(LoteServiceInterface::class, MockLoteService::class);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        $operario = Operario::factory()->create(['user_id' => $user->id]);
        $sessao   = SessaoTrabalho::factory()->create([
            'operario_id' => $operario->id,
            'maquina_id'  => $maquina->id,
        ]);
        $apontamento = Apontamento::factory()->emProducao()->create([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapa->id,
            'cod_peca'           => '4501940',
            'ordem_lote'         => '06854',
            'producao_inicio'    => now(),
        ]);

        // Produto original: pilha 1
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501940',
                'ordem_lote' => '06854',
                'qtd_peca'   => 50,
                'pilha'      => 1,
            ])
            ->assertStatus(200);

        // Variante: pilha 1 (mesmo número, cod_peca diferente)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamento->id}/bipar-ficha", [
                'cod_peca'   => '4501962',
                'ordem_lote' => '06854',
                'qtd_peca'   => 48,
                'pilha'      => 1,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $fichas      = $response->json('apontamento.fichas');
        $totalBipado = collect($fichas)->sum('qtd_peca');

        $this->assertEquals(98, $totalBipado);
        $this->assertCount(2, $fichas);
    }
}
