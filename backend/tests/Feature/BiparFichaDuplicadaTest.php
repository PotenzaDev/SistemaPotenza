<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\Turno;
use App\Models\User;
use App\Services\Lote\LoteServiceInterface;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiparFichaDuplicadaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_bipar_a_mesma_pilha_duas_vezes_exige_confirmacao_e_depois_permite(): void
    {
        // Bridge indica 2 fichas físicas legítimas para este lote/produto.
        [$user, $apontamentoId] = $this->prepararApontamentoEmProducao(passagensEsperadas: 2);

        $payload = ['cod_peca' => '4501940', 'ordem_lote' => '06854', 'qtd_peca' => 10, 'pilha' => 1];

        // Primeira ficha da pilha 1: passa direto.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamentoId}/bipar-ficha", $payload)
            ->assertOk();

        // Segunda ficha, mesma pilha, sem confirmar: exige confirmação (409), não erro de banco.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamentoId}/bipar-ficha", $payload)
            ->assertStatus(409)
            ->assertJsonPath('requiresConfirmation', true)
            ->assertJsonPath('passagensEsperadas', 2);

        // Confirmando, a segunda ficha da mesma pilha é aceita (era aqui que a constraint travava).
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamentoId}/bipar-ficha", $payload + ['confirmar' => true])
            ->assertOk()
            ->assertJsonCount(2, 'data.fichas');

        $this->assertDatabaseCount('fichas_apontamento', 2);
    }

    public function test_bipar_a_mesma_pilha_apos_atingir_limite_da_bridge_permanece_bloqueado(): void
    {
        // Bridge indica que só existe 1 ficha física legítima para este lote/produto
        // (linhas da view FbmLoteFichaTecnica não são exatamente iguais entre si).
        [$user, $apontamentoId] = $this->prepararApontamentoEmProducao(passagensEsperadas: 1);

        $payload = ['cod_peca' => '4501940', 'ordem_lote' => '06854', 'qtd_peca' => 10, 'pilha' => 1];

        // Primeira ficha da pilha 1: passa direto.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamentoId}/bipar-ficha", $payload)
            ->assertOk();

        // Segunda ficha, mesma pilha: já atingiu o limite da Bridge, bloqueia mesmo confirmando.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamentoId}/bipar-ficha", $payload + ['confirmar' => true])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Pilha 1 já atingiu o limite de 1 passagem(ns) neste lote.']);

        $this->assertDatabaseCount('fichas_apontamento', 1);
    }

    /**
     * Cria turno, etapa, máquina e operário; inicia sessão; bipa o lote e
     * finaliza o setup, deixando o apontamento pronto para bipar fichas.
     * A Bridge é mockada para retornar $passagensEsperadas em contarFichasLote.
     *
     * @return array{0: User, 1: int} [$user, $apontamentoId]
     */
    private function prepararApontamentoEmProducao(int $passagensEsperadas): array
    {
        $this->app->bind(LoteServiceInterface::class, fn () => new class($passagensEsperadas) implements LoteServiceInterface {
            public function __construct(private readonly int $passagensEsperadas) {}

            public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array
            {
                return ['cod_produto' => 'PROD-0001', 'desc_peca' => 'Peça Teste', 'qtde_total' => null];
            }

            public function buscarFtecPecaPilha(string $codPeca): ?int
            {
                return null;
            }

            public function contarFichasLote(string $ordemLote, string $codPeca): int
            {
                return $this->passagensEsperadas;
            }

            public function buscarTotaisPorPrefixoLote(string $ordemLote, string $prefixoCod): array
            {
                return ['qtde_total' => null, 'total_pilhas' => 0];
            }
        });

        $t0 = Carbon::parse('2026-07-02 08:00:00');
        Carbon::setTestNow($t0);

        Turno::create([
            'dia_semana'                      => $t0->dayOfWeekIso,
            'vigente_desde'                   => $t0->copy()->subDay()->toDateString(),
            'hora_inicio'                      => '00:00:00',
            'hora_fim'                         => '23:59:00',
            'tolerancia_finalizacao_minutos'   => 0,
            'ativo'                            => true,
        ]);

        $etapa    = EtapaFluxo::factory()->create(['ativa' => true]);
        $maquina  = Maquina::factory()->create(['etapa_fluxo_id' => $etapa->id, 'ativa' => true]);
        $user     = User::factory()->operario()->create();
        Operario::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sessao/iniciar', ['maquina_id' => $maquina->id])
            ->assertCreated();

        $bipar = $this->actingAs($user, 'sanctum')
            ->postJson('/api/apontamento/bipar', ['cod_peca' => '4501940', 'ordem_lote' => '06854'])
            ->assertCreated();

        $apontamentoId = $bipar->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/apontamento/{$apontamentoId}/finalizar-setup")
            ->assertOk();

        return [$user, $apontamentoId];
    }
}
