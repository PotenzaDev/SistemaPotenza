<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Apontamento;
use App\Models\EventoSessao;
use App\Models\MotivoPausa;
use App\Models\Operario;
use App\Models\Pausa;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use App\Repositories\Contracts\FichaApontamentoRepositoryInterface;
use App\Repositories\Contracts\HistoricoLoteRepositoryInterface;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use App\Services\Lote\LoteServiceInterface;
use Carbon\Carbon;

class ApontamentoService
{
    public function __construct(
        private readonly ApontamentoRepositoryInterface      $apontamentoRepo,
        private readonly FichaApontamentoRepositoryInterface $fichaRepo,
        private readonly SessaoTrabalhoRepositoryInterface   $sessaoRepo,
        private readonly HistoricoLoteRepositoryInterface    $historicoRepo,
        private readonly LoteServiceInterface                $loteService,
    ) {}

    /**
     * Bipar do LOTE para iniciar setup.
     * Cria o Apontamento em status em_setup e inicia o timer de setup automaticamente.
     */
    public function bipar(Operario $operario, array $dados): Apontamento
    {
        $sessao = $this->sessaoRepo->buscarSessaoAtiva($operario);

        if (! $sessao) {
            throw new BusinessException('Operário não possui sessão ativa. Selecione uma máquina primeiro.', 422);
        }

        if ($this->apontamentoRepo->buscarApontamentoAtivo($sessao)) {
            throw new BusinessException('Já existe um apontamento em andamento. Finalize-o antes de iniciar novo lote.', 422);
        }

        $loteDados     = $this->loteService->buscarPorOrdemLote($dados['ordem_lote'], $dados['cod_peca']);
        $ftecPecaPilha = $this->loteService->buscarFtecPecaPilha($dados['cod_peca']);

        $etapaFluxoId = $sessao->maquina->etapa_fluxo_id;

        if ($loteDados['qtde_total'] && $ftecPecaPilha) {
            $totalPilhas   = (int) ceil($loteDados['qtde_total'] / $ftecPecaPilha);
            $pilhasBipadas = $this->fichaRepo->contarPilhasBipadasDoLote(
                $dados['ordem_lote'],
                $dados['cod_peca'],
                $etapaFluxoId,
            );

            if ($pilhasBipadas >= $totalPilhas) {
                throw new BusinessException(
                    "Todas as {$totalPilhas} pilhas deste lote já foram processadas nesta etapa.",
                    422
                );
            }
        }

        $apontamento = $this->apontamentoRepo->criar([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapaFluxoId,
            'cod_peca'           => $dados['cod_peca'],
            'ordem_lote'         => $dados['ordem_lote'],
            'desc_peca'          => $loteDados['desc_peca'],
            'cod_produto'        => $loteDados['cod_produto'],
            'qtde_total'         => $loteDados['qtde_total'] ?? null,
            'ftec_peca_pilha'    => $ftecPecaPilha,
            'status'             => Apontamento::STATUS_EM_SETUP,
            'setup_inicio'       => Carbon::now(),
        ]);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Finaliza o setup e aguarda a bipagem da primeira ficha de produção.
     * Status: em_setup → aguardando_producao
     */
    public function finalizarSetup(Apontamento $apontamento): Apontamento
    {
        if ($apontamento->status !== Apontamento::STATUS_EM_SETUP) {
            throw new BusinessException('Apontamento não está em setup.', 422);
        }

        if (! $apontamento->setup_inicio || $apontamento->setup_fim !== null) {
            throw new BusinessException('Setup não iniciado ou já finalizado.', 422);
        }

        $fim     = Carbon::now();
        $duracao = $this->duracaoLiquida($apontamento, 'setup', $fim);

        $apontamento->update([
            'setup_fim'              => $fim,
            'setup_duracao_segundos' => $duracao,
            'status'                 => Apontamento::STATUS_AGUARDANDO_PRODUCAO,
        ]);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Bipar ficha durante a produção.
     * Válido nos status: aguardando_producao ou em_producao.
     * Se for a primeira ficha, inicia producao_inicio.
     * Fecha o timer da ficha anterior antes de criar a nova.
     */
    public function biparFicha(Apontamento $apontamento, array $dados): Apontamento
    {
        if (! in_array($apontamento->status, [
            Apontamento::STATUS_AGUARDANDO_PRODUCAO,
            Apontamento::STATUS_EM_PRODUCAO,
        ], true)) {
            throw new BusinessException('Apontamento não está aguardando ou em produção.', 422);
        }

        if ($dados['ordem_lote'] !== $apontamento->ordem_lote) {
            throw new BusinessException(
                "Esta ficha é do lote {$dados['ordem_lote']}, mas o apontamento ativo é do lote {$apontamento->ordem_lote}.",
                422
            );
        }

        if ($dados['cod_peca'] !== $apontamento->cod_peca) {
            throw new BusinessException(
                "Esta ficha é do produto {$dados['cod_peca']}, diferente do apontamento ativo ({$apontamento->cod_peca}).",
                422
            );
        }

        $pilha = (int) $dados['pilha'];

        if ($this->fichaRepo->pilhaJaBipada(
            $apontamento->ordem_lote,
            $apontamento->cod_peca,
            $apontamento->etapa_fluxo_id,
            $pilha,
        )) {
            throw new BusinessException("Pilha {$pilha} já foi bipada neste lote.", 422);
        }

        // Marco de tempo compartilhado: fim da ficha anterior = inicio desta
        $agora = Carbon::now();

        // Fecha o timer da ficha anterior (sem fim_producao)
        $fichaAnterior = $apontamento->fichas()
            ->whereNull('fim_producao')
            ->latest('bipada_at')
            ->first();

        if ($fichaAnterior) {
            $this->fichaRepo->fecharFicha($fichaAnterior->id, $agora);
        }

        $this->fichaRepo->criar([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => $dados['cod_peca'],
            'pilha'          => $pilha,
            'qtd_peca'       => (int) $dados['qtd_peca'],
            'bipada_at'      => $agora,
        ]);

        // Primeira ficha: inicia producao_inicio e muda status
        if ($apontamento->status === Apontamento::STATUS_AGUARDANDO_PRODUCAO) {
            $apontamento->update([
                'producao_inicio' => $agora,
                'status'          => Apontamento::STATUS_EM_PRODUCAO,
            ]);
        }

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Finaliza a produção.
     * Recebe array de [{ficha_id, qtd_produzida}] para cada ficha bipada.
     */
    public function finalizar(Apontamento $apontamento, array $fichasQtd): Apontamento
    {
        if ($apontamento->status !== Apontamento::STATUS_EM_PRODUCAO) {
            throw new BusinessException('Apontamento não está em produção.', 422);
        }

        if (! $apontamento->producao_inicio || $apontamento->producao_fim !== null) {
            throw new BusinessException('Produção não iniciada ou já finalizada.', 422);
        }

        // Garante que todas as peças do lote foram bipadas antes de finalizar.
        $qtdeTotal   = $apontamento->qtde_total;
        $totalBipado = $apontamento->fichas->sum('qtd_peca');

        if ($qtdeTotal && $totalBipado < $qtdeTotal) {
            throw new BusinessException(
                "Bipe todas as fichas antes de finalizar. Bipado: {$totalBipado} de {$qtdeTotal} peças.",
                422
            );
        }

        foreach ($fichasQtd as $item) {
            $this->fichaRepo->atualizarQtdProduzida(
                (int) $item['ficha_id'],
                (int) $item['qtd_produzida']
            );
        }

        $fim     = Carbon::now();
        $duracao = $this->duracaoLiquida($apontamento, 'producao', $fim);

        // Fecha o timer da última ficha em aberto (mesmo timestamp do fim da produção)
        $ultimaFicha = $apontamento->fichas()
            ->whereNull('fim_producao')
            ->latest('bipada_at')
            ->first();

        if ($ultimaFicha) {
            $this->fichaRepo->fecharFicha($ultimaFicha->id, $fim);
        }

        $totalPausas = (int) $apontamento->pausas()->whereNotNull('fim')->sum('duracao_segundos');

        $apontamento->update([
            'producao_fim'              => $fim,
            'producao_duracao_segundos' => $duracao,
            'total_pausa_segundos'      => $totalPausas,
            'status'                    => Apontamento::STATUS_FINALIZADO,
        ]);

        $apontamento->load(['etapaFluxo', 'fichas']);

        $this->atualizarHistoricoLote($apontamento);

        return $apontamento;
    }

    /**
     * Pausa o apontamento em setup ou em produção.
     */
    public function pausar(Apontamento $apontamento, int $motivoId, bool $sistema = false): Apontamento
    {
        $statusValidos = [Apontamento::STATUS_EM_SETUP, Apontamento::STATUS_EM_PRODUCAO];

        if (! in_array($apontamento->status, $statusValidos, true)) {
            throw new BusinessException('Apontamento não pode ser pausado no status atual.', 422);
        }

        if ($apontamento->pausas()->whereNull('fim')->exists()) {
            throw new BusinessException('Já existe uma pausa em aberto neste apontamento.', 422);
        }

        $motivo = MotivoPausa::find($motivoId);

        if (! $motivo || (! $sistema && ! $motivo->ativo)) {
            throw new BusinessException('Motivo de pausa inválido.', 422);
        }

        $fase = $apontamento->status === Apontamento::STATUS_EM_SETUP ? 'setup' : 'producao';

        Pausa::create([
            'apontamento_id'  => $apontamento->id,
            'motivo_pausa_id' => $motivoId,
            'fase'            => $fase,
            'inicio'          => Carbon::now(),
        ]);

        $novoStatus = $fase === 'setup'
            ? Apontamento::STATUS_EM_PAUSA_SETUP
            : Apontamento::STATUS_EM_PAUSA_PRODUCAO;

        $apontamento->update(['status' => $novoStatus]);

        $this->sessaoRepo->registrarEvento($apontamento->sessao_trabalho_id, EventoSessao::TIPO_PAUSA, $apontamento->id);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Retoma um apontamento pausado, fechando a pausa em aberto.
     */
    public function retomar(Apontamento $apontamento): Apontamento
    {
        $statusValidos = [Apontamento::STATUS_EM_PAUSA_SETUP, Apontamento::STATUS_EM_PAUSA_PRODUCAO];

        if (! in_array($apontamento->status, $statusValidos, true)) {
            throw new BusinessException('Apontamento não está pausado.', 422);
        }

        $pausaAberta = $apontamento->pausas()->whereNull('fim')->first();

        if ($pausaAberta) {
            $fim     = Carbon::now();
            $duracao = (int) $pausaAberta->inicio->diffInSeconds($fim);
            $pausaAberta->update(['fim' => $fim, 'duracao_segundos' => $duracao]);
        }

        $novoStatus = $apontamento->status === Apontamento::STATUS_EM_PAUSA_SETUP
            ? Apontamento::STATUS_EM_SETUP
            : Apontamento::STATUS_EM_PRODUCAO;

        $totalPausas = (int) $apontamento->pausas()->whereNotNull('fim')->sum('duracao_segundos');

        $apontamento->update([
            'status'               => $novoStatus,
            'total_pausa_segundos' => $totalPausas,
        ]);

        $this->sessaoRepo->registrarEvento($apontamento->sessao_trabalho_id, EventoSessao::TIPO_RETOMADA, $apontamento->id);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Apontamentos do período/critérios informados — ou de hoje, na ausência
     * de filtros — formatados para a listagem gerencial com operário, máquina,
     * quantidades, tempos e horários de cada fase.
     *
     * Filtros aceitos (todos opcionais): data_inicio, data_fim (Y-m-d),
     * operario_id, maquina_id, grupo_id, ordem_lote.
     */
    public function listarApontamentos(array $filtros = []): array
    {
        $apontamentos = $this->apontamentoRepo->apontamentosDoDia($filtros);

        $linhas = $apontamentos->map(function (Apontamento $apontamento) {
            $qtdPecas  = (int) $apontamento->fichas->sum('qtd_peca');
            $qtdPilhas = $apontamento->fichas->count();
            $grupo     = $apontamento->sessaoTrabalho?->maquina?->etapaFluxo;

            return [
                'id'                      => $apontamento->id,
                'cod_peca'                => $apontamento->cod_peca,
                'ordem_lote'              => $apontamento->ordem_lote,
                'desc_peca'               => $apontamento->desc_peca,
                'status'                  => $apontamento->status,
                'operario'                => $apontamento->sessaoTrabalho?->operario?->user?->name,
                'maquina'                 => $apontamento->sessaoTrabalho?->maquina?->nome,
                'grupo'                   => $grupo ? ['id' => $grupo->id, 'nome' => $grupo->nome] : null,
                'qtd_pecas'               => $qtdPecas,
                'qtd_pilhas'              => $qtdPilhas,
                'tempo_setup_segundos'    => $apontamento->setup_duracao_segundos,
                'tempo_producao_segundos' => $apontamento->producao_duracao_segundos,
                'numero_pausas'           => $apontamento->pausas->count(),
                'setup_inicio'            => $apontamento->setup_inicio?->toIso8601String(),
                'setup_fim'               => $apontamento->setup_fim?->toIso8601String(),
                'producao_inicio'         => $apontamento->producao_inicio?->toIso8601String(),
                'producao_fim'            => $apontamento->producao_fim?->toIso8601String(),
                'created_at'              => $apontamento->created_at?->toIso8601String(),
            ];
        })->values();

        return [
            'apontamentos' => $linhas->all(),
            'totais'       => [
                'qtd_pecas'  => (int) $linhas->sum('qtd_pecas'),
                'qtd_pilhas' => (int) $linhas->sum('qtd_pilhas'),
            ],
        ];
    }

    /** Duração líquida = (fim - inicio) menos o total de pausas da fase. */
    private function duracaoLiquida(Apontamento $apontamento, string $fase, Carbon $fim): int
    {
        $inicio = $fase === 'setup' ? $apontamento->setup_inicio : $apontamento->producao_inicio;
        $bruto  = (int) $inicio->diffInSeconds($fim);
        $pausas = (int) $apontamento->pausas->where('fase', $fase)->sum('duracao_segundos');

        return max(0, $bruto - $pausas);
    }

    private function atualizarHistoricoLote(Apontamento $apontamento): void
    {
        $historico = $this->historicoRepo->buscarOuCriar(
            $apontamento->etapa_fluxo_id,
            $apontamento->cod_peca,
            $apontamento->ordem_lote
        );

        $this->historicoRepo->incrementarPilhaConcluida($historico);

        $loteDados = $this->loteService->buscarPorOrdemLote($apontamento->ordem_lote, $apontamento->cod_peca);
        $totalProd = $this->apontamentoRepo->somarQtdProduzida(
            $apontamento->etapa_fluxo_id,
            $apontamento->ordem_lote
        );

        if ($loteDados['qtde_total'] && $totalProd >= $loteDados['qtde_total']) {
            $this->historicoRepo->concluir($historico);
        }
    }
}
