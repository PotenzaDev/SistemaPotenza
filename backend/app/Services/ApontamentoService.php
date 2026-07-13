<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\ConfirmacaoNecessariaException;
use App\Exceptions\LoteCompletoException;
use App\Models\Apontamento;
use App\Models\EventoSessao;
use App\Models\FichaApontamento;
use App\Models\MotivoPausa;
use App\Models\Operario;
use App\Models\FichaCabecote;
use App\Models\Pausa;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use App\Repositories\Contracts\FichaApontamentoRepositoryInterface;
use App\Repositories\Contracts\HistoricoLoteRepositoryInterface;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use App\Models\Turno;
use App\Services\Lote\LoteServiceInterface;
use App\Services\Produto\ProdutoPecaLookupService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ApontamentoService
{
    public function __construct(
        private readonly ApontamentoRepositoryInterface      $apontamentoRepo,
        private readonly FichaApontamentoRepositoryInterface $fichaRepo,
        private readonly SessaoTrabalhoRepositoryInterface   $sessaoRepo,
        private readonly HistoricoLoteRepositoryInterface    $historicoRepo,
        private readonly LoteServiceInterface                $loteService,
        private readonly TurnoCalculoService                 $turnoCalculo,
        private readonly ProdutoPecaLookupService             $produtoPecaLookup,
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

        if ($sessao->pausaOciosaAberta()->exists()) {
            throw new BusinessException('Sessão está pausada. Retome antes de bipar um novo lote.', 422);
        }

        $loteDados       = $this->loteService->buscarPorOrdemLote($dados['ordem_lote'], $dados['cod_peca']);
        $ftecPecaPilha   = $this->loteService->buscarFtecPecaPilha($dados['cod_peca']);
        $totaisVariantes = $this->loteService->buscarTotaisPorPrefixoLote(
            $dados['ordem_lote'],
            substr($dados['cod_peca'], 0, 5),
        );

        // Totaliza peças e pilhas considerando todos os produtos variantes do lote (mesmo prefixo).
        // Fallback ao dado individual caso a bridge não retorne resultado agregado.
        $qtdeTotal   = $totaisVariantes['qtde_total'] ?? $loteDados['qtde_total'];
        $totalPilhas = $totaisVariantes['total_pilhas'];

        if ($totalPilhas === 0 && $loteDados['qtde_total'] && $ftecPecaPilha) {
            $totalPilhas = (int) ceil($loteDados['qtde_total'] / $ftecPecaPilha);
        }

        $etapaFluxoId = $sessao->maquina->etapa_fluxo_id;

        if ($totalPilhas > 0) {
            $pilhasBipadas = $this->fichaRepo->contarPilhasBipadasDoLote(
                $dados['ordem_lote'],
                $dados['cod_peca'],
                $etapaFluxoId,
            );

            if ($pilhasBipadas >= $totalPilhas) {
                throw new LoteCompletoException(
                    "Todas as {$totalPilhas} pilhas deste lote já foram processadas nesta etapa.",
                    $pilhasBipadas,
                    $totalPilhas,
                );
            }
        }

        $possuiSetup = $sessao->maquina->regraMaquina?->possui_setup ?? true;

        $apontamento = $this->apontamentoRepo->criar([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapaFluxoId,
            'cod_peca'           => $dados['cod_peca'],
            'ordem_lote'         => $dados['ordem_lote'],
            'desc_peca'          => $loteDados['desc_peca'],
            'cod_produto'        => $loteDados['cod_produto'],
            'qtde_total'         => $qtdeTotal,
            'ftec_peca_pilha'    => $ftecPecaPilha,
            'status'             => $possuiSetup ? Apontamento::STATUS_EM_SETUP : Apontamento::STATUS_AGUARDANDO_PRODUCAO,
            'setup_inicio'       => $possuiSetup ? Carbon::now() : null,
        ]);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Inicia uma segunda (ou N-ésima) passagem do mesmo lote na mesma etapa.
     * Requer que exista um apontamento finalizado para o lote/etapa.
     * Cria um novo Apontamento vinculado ao original, com numero_passagem incrementado.
     */
    public function iniciarSegundaPassagem(Operario $operario, array $dados): Apontamento
    {
        $sessao = $this->sessaoRepo->buscarSessaoAtiva($operario);

        if (! $sessao) {
            throw new BusinessException('Operário não possui sessão ativa. Selecione uma máquina primeiro.', 422);
        }

        if ($this->apontamentoRepo->buscarApontamentoAtivo($sessao)) {
            throw new BusinessException('Já existe um apontamento em andamento. Finalize-o antes de iniciar nova passagem.', 422);
        }

        $etapaFluxoId = $sessao->maquina->etapa_fluxo_id;

        $origem = $this->apontamentoRepo->buscarUltimoFinalizadoPorLoteEtapa(
            $dados['ordem_lote'],
            $dados['cod_peca'],
            $etapaFluxoId,
        );

        if (! $origem) {
            throw new BusinessException('Nenhuma passagem anterior finalizada encontrada para este lote nesta etapa.', 422);
        }

        $regras = $sessao->maquina->regraMaquina;

        if ($regras && ! $regras->permite_multiplas_passagens) {
            throw new BusinessException('Esta máquina não permite mais de uma passagem por ficha.', 422);
        }

        $proximaPassagem = $origem->numero_passagem + 1;

        if ($regras && $regras->limite_passagens !== null && $proximaPassagem > $regras->limite_passagens) {
            throw new BusinessException("Limite de {$regras->limite_passagens} passagens atingido nesta máquina.", 422);
        }

        $possuiSetup = $regras?->possui_setup ?? true;

        $apontamento = $this->apontamentoRepo->criar([
            'sessao_trabalho_id'   => $sessao->id,
            'etapa_fluxo_id'       => $etapaFluxoId,
            'cod_peca'             => $origem->cod_peca,
            'ordem_lote'           => $origem->ordem_lote,
            'desc_peca'            => $origem->desc_peca,
            'cod_produto'          => $origem->cod_produto,
            'qtde_total'           => $origem->qtde_total,
            'ftec_peca_pilha'      => $origem->ftec_peca_pilha,
            'status'               => $possuiSetup ? Apontamento::STATUS_EM_SETUP : Apontamento::STATUS_AGUARDANDO_PRODUCAO,
            'setup_inicio'         => $possuiSetup ? Carbon::now() : null,
            'numero_passagem'      => $proximaPassagem,
            'apontamento_origem_id' => $origem->id,
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

        $fim           = Carbon::now();
        $duracaoJanela = $this->duracaoLiquida($apontamento, 'setup', $fim);
        $duracaoTotal  = (int) $apontamento->setup_duracao_segundos + $duracaoJanela;

        $apontamento->update([
            'setup_fim'              => $fim,
            'setup_duracao_segundos' => $duracaoTotal,
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
    public function biparFicha(Apontamento $apontamento, array $dados, bool $confirmar = false): Apontamento
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

        if (substr($dados['cod_peca'], 0, 5) !== substr($apontamento->cod_peca, 0, 5)) {
            throw new BusinessException(
                "Esta ficha é do produto {$dados['cod_peca']}, incompatível com o apontamento ativo ({$apontamento->cod_peca}).",
                422
            );
        }

        $pilha = (int) $dados['pilha'];

        // Bipagem duplicada acidental: mesma pilha já bipada NESTE apontamento.
        // Compara contra o total de fichas físicas esperadas (bridge) — limite rígido.
        $vezesBipadaAtual = $this->fichaRepo->contarVezesPilhaBipadaNoApontamento(
            $apontamento->id,
            $dados['cod_peca'],
            $pilha,
        );

        if ($vezesBipadaAtual > 0) {
            $passagensEsperadas = $this->loteService->contarFichasLote(
                $apontamento->ordem_lote,
                $dados['cod_peca'],
            );

            if ($vezesBipadaAtual >= $passagensEsperadas) {
                throw new BusinessException(
                    "Pilha {$pilha} já atingiu o limite de {$passagensEsperadas} passagem(ns) neste lote.",
                    422
                );
            }

            if (! $confirmar) {
                throw new ConfirmacaoNecessariaException(
                    "Pilha {$pilha} já foi bipada neste lote. Deseja registrar uma nova passagem?",
                    $vezesBipadaAtual,
                    $passagensEsperadas,
                );
            }
        } elseif (! $confirmar) {
            // Repasse legítimo: pilha já bipada em um apontamento ANTERIOR já finalizado
            // (ex.: retrabalho, nova passagem pela mesma etapa). Sempre confirmável — não
            // há limite da bridge aqui, pois o limite representa fichas físicas distintas,
            // não quantas vezes a peça pode reprocessar.
            $vezesBipadaAnterior = $this->fichaRepo->contarVezesPilhaBipadaEmOutrosApontamentos(
                $apontamento->ordem_lote,
                $dados['cod_peca'],
                $apontamento->etapa_fluxo_id,
                $pilha,
                $apontamento->id,
            );

            if ($vezesBipadaAnterior > 0) {
                throw new ConfirmacaoNecessariaException(
                    "Esta ficha já passou por esta etapa em um apontamento anterior. Deseja processá-la novamente?",
                    $vezesBipadaAnterior,
                    $vezesBipadaAnterior + 1,
                );
            }
        }

        // Marco de tempo compartilhado: fim da ficha anterior = inicio desta
        $agora = Carbon::now();

        // Fecha o timer da ficha anterior (sem fim_producao)
        $fichaAnterior = $apontamento->fichas()
            ->whereNull('fim_producao')
            ->latest('bipada_at')
            ->first();

        if ($fichaAnterior) {
            $this->fichaRepo->fecharFicha($fichaAnterior->id, $agora, $fichaAnterior->qtd_peca);
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

        $qtdeTotal   = $apontamento->qtde_total;
        $totalBipado = $apontamento->fichas->sum('qtd_peca');

        if ($qtdeTotal && $totalBipado < $qtdeTotal) {
            throw new BusinessException(
                "Bipe todas as fichas antes de finalizar. Bipado: {$totalBipado} de {$qtdeTotal} peças.",
                422
            );
        }

        // Gate por cor: o total acima é a soma de todas as cores da peça (mesmo
        // prefixo de 5 dígitos), então pode bater mesmo faltando uma cor inteira.
        // Quando a Bridge responde com o detalhe por cor, exige que cada uma
        // tenha atingido sua própria quantidade antes de liberar a finalização.
        $progresso = $this->progressoPorCor($apontamento);
        $pendentes = array_filter($progresso, fn (array $p) => $p['falta'] > 0);

        if ($pendentes !== []) {
            $lista = implode('; ', array_map(
                fn (array $p) => "{$p['cor']} ({$p['cod_peca']}): {$p['qtd_bipada']}/{$p['qtde_total']}",
                $pendentes
            ));

            throw new BusinessException(
                "Bipe todas as cores antes de finalizar. Pendente — {$lista}.",
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
     * Finaliza direto de aguardando_producao, sem bipagem individual de fichas.
     * Só é permitido quando a máquina está configurada com possui_producao=false.
     * Cria uma ficha sintética com a qtde_total do lote como produzida.
     */
    public function finalizarSemProducao(Apontamento $apontamento): Apontamento
    {
        if ($apontamento->status !== Apontamento::STATUS_AGUARDANDO_PRODUCAO) {
            throw new BusinessException('Apontamento não está aguardando produção.', 422);
        }

        $regras = $apontamento->sessaoTrabalho->maquina->regraMaquina;

        if ($regras && $regras->possui_producao) {
            throw new BusinessException('Esta máquina exige bipagem de fichas antes de finalizar.', 422);
        }

        $agora     = Carbon::now();
        $qtdeTotal = (int) ($apontamento->qtde_total ?? 0);

        $this->fichaRepo->criar([
            'apontamento_id'   => $apontamento->id,
            'cod_peca'         => $apontamento->cod_peca,
            'pilha'            => 1,
            'qtd_peca'         => $qtdeTotal,
            'qtd_produzida'    => $qtdeTotal,
            'bipada_at'        => $agora,
            'fim_producao'     => $agora,
            'duracao_segundos' => 0,
        ]);

        $totalPausas = (int) $apontamento->pausas()->whereNotNull('fim')->sum('duracao_segundos');

        $apontamento->update([
            'producao_inicio'           => $agora,
            'producao_fim'              => $agora,
            'producao_duracao_segundos' => 0,
            'total_pausa_segundos'      => $totalPausas,
            'status'                    => Apontamento::STATUS_FINALIZADO,
        ]);

        $apontamento->load(['etapaFluxo', 'fichas']);

        $this->atualizarHistoricoLote($apontamento);

        return $apontamento;
    }

    /**
     * Pausa o apontamento em setup, aguardando produção ou em produção.
     */
    public function pausar(Apontamento $apontamento, int $motivoId, bool $sistema = false): Apontamento
    {
        $fase = Apontamento::MAPA_FASE[$apontamento->status] ?? null;

        if ($fase === null) {
            throw new BusinessException('Apontamento não pode ser pausado no status atual.', 422);
        }

        if ($apontamento->pausas()->whereNull('fim')->exists()) {
            throw new BusinessException('Já existe uma pausa em aberto neste apontamento.', 422);
        }

        $motivo = MotivoPausa::find($motivoId);

        if (! $motivo || (! $sistema && ! $motivo->ativo)) {
            throw new BusinessException('Motivo de pausa inválido.', 422);
        }

        Pausa::create([
            'apontamento_id'  => $apontamento->id,
            'motivo_pausa_id' => $motivoId,
            'fase'            => $fase,
            'inicio'          => Carbon::now(),
        ]);

        $novoStatus = match ($fase) {
            'setup'      => Apontamento::STATUS_EM_PAUSA_SETUP,
            'aguardando' => Apontamento::STATUS_EM_PAUSA_AGUARDANDO,
            'producao'   => Apontamento::STATUS_EM_PAUSA_PRODUCAO,
        };

        $apontamento->update(['status' => $novoStatus]);

        $this->sessaoRepo->registrarEvento($apontamento->sessao_trabalho_id, EventoSessao::TIPO_PAUSA, $apontamento->id);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Auto-pausa de sistema: chamada via sendBeacon ao fechar o navegador.
     * Usa o motivo is_sistema=true; não requer escolha do operário. Se já
     * pausado, ignora silenciosamente (idempotente — o beacon pode disparar
     * mais de uma vez para o mesmo fechamento de aba).
     */
    public function pausarSistema(Apontamento $apontamento): Apontamento
    {
        if (in_array($apontamento->status, [
            Apontamento::STATUS_EM_PAUSA_SETUP,
            Apontamento::STATUS_EM_PAUSA_PRODUCAO,
        ], true)) {
            return $apontamento;
        }

        $motivoSistema = MotivoPausa::where('is_sistema', true)->first();

        if (! $motivoSistema) {
            throw new BusinessException('Motivo de sistema não configurado.', 500);
        }

        return $this->pausar($apontamento, $motivoSistema->id, true);
    }

    /**
     * Retoma um apontamento pausado, fechando a pausa em aberto.
     */
    public function retomar(Apontamento $apontamento): Apontamento
    {
        $novoStatus = Apontamento::MAPA_RETOMADA[$apontamento->status] ?? null;

        if ($novoStatus === null) {
            throw new BusinessException('Apontamento não está pausado.', 422);
        }

        $pausaAberta = $apontamento->pausas()->whereNull('fim')->first();

        if ($pausaAberta) {
            $fim     = Carbon::now();
            $duracao = (int) $pausaAberta->inicio->diffInSeconds($fim);
            $pausaAberta->update(['fim' => $fim, 'duracao_segundos' => $duracao]);
        }

        $totalPausas = (int) $apontamento->pausas()->whereNotNull('fim')->sum('duracao_segundos');

        $apontamento->update([
            'status'               => $novoStatus,
            'total_pausa_segundos' => $totalPausas,
        ]);

        $this->sessaoRepo->registrarEvento($apontamento->sessao_trabalho_id, EventoSessao::TIPO_RETOMADA, $apontamento->id);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Retoma um apontamento pausado por pausa de sessão, forçando uma nova
     * janela de setup antes de continuar — mesmo que estivesse em produção
     * no momento em que a sessão foi pausada.
     */
    public function retomarComNovoSetup(Apontamento $apontamento): Apontamento
    {
        $statusValidos = Apontamento::statusPausados();

        if (! in_array($apontamento->status, $statusValidos, true)) {
            throw new BusinessException('Apontamento não está pausado.', 422);
        }

        $estavaEmSetup = $apontamento->status === Apontamento::STATUS_EM_PAUSA_SETUP;
        $pausaAberta   = $apontamento->pausas()->whereNull('fim')->first();
        $agora         = Carbon::now();

        // A janela de setup interrompida pela pausa de sessão é abandonada, mas o
        // tempo já trabalhado nela até o início da pausa precisa ser preservado.
        $duracaoJanelaAnterior = $estavaEmSetup
            ? $this->duracaoLiquida($apontamento, 'setup', $pausaAberta?->inicio ?? $agora)
            : 0;

        if ($pausaAberta) {
            $duracao = (int) $pausaAberta->inicio->diffInSeconds($agora);
            $pausaAberta->update(['fim' => $agora, 'duracao_segundos' => $duracao]);
        }

        $totalPausas = (int) $apontamento->pausas()->whereNotNull('fim')->sum('duracao_segundos');

        $apontamento->update([
            'status'                 => Apontamento::STATUS_EM_SETUP,
            'setup_inicio'           => $agora,
            'setup_fim'              => null,
            'setup_duracao_segundos' => (int) $apontamento->setup_duracao_segundos + $duracaoJanelaAnterior,
            'total_pausa_segundos'   => $totalPausas,
        ]);

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
        ['inicio' => $inicio, 'fim' => $fim] = $this->apontamentoRepo->resolverPeriodo($filtros);

        $linhas = $apontamentos->map(function (Apontamento $apontamento) use ($inicio, $fim) {
            $fichasNoPeriodo = $this->fichasNoPeriodo($apontamento, $inicio, $fim);

            $qtdPecas  = (int) $fichasNoPeriodo->sum('qtd_peca');
            $qtdPilhas = $fichasNoPeriodo->count();
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
                'tempo_setup_segundos'    => $this->tempoNaTurno($apontamento, 'setup'),
                'tempo_producao_segundos' => $this->tempoNaTurno($apontamento, 'producao'),
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

    /**
     * Detalhe do apontamento com as fichas restritas ao período filtrado —
     * mesma regra de listarApontamentos(): só entram fichas cujo fim_producao
     * caiu dentro do período (hoje, na ausência de filtros). Evita que o
     * modal de detalhe mostre pilhas de um dia diferente do selecionado no
     * relatório, quando o apontamento atravessa a virada do dia.
     *
     * Filtros aceitos (todos opcionais): data_inicio, data_fim (Y-m-d).
     */
    public function buscarDetalhe(Apontamento $apontamento, array $filtros = []): Apontamento
    {
        ['inicio' => $inicio, 'fim' => $fim] = $this->apontamentoRepo->resolverPeriodo($filtros);

        // Recarrega a relação a partir do banco antes de filtrar: o mesmo
        // $apontamento pode ser reutilizado em chamadas sucessivas com
        // filtros diferentes, e setRelation() abaixo sobrescreve 'fichas' em
        // definitivo — sem o reload, a segunda chamada filtraria em cima do
        // subconjunto já filtrado pela primeira.
        $apontamento->load('fichas');
        $apontamento->setRelation('fichas', $this->fichasNoPeriodo($apontamento, $inicio, $fim)->values());

        return $apontamento;
    }

    /**
     * Fichas do apontamento cujo fim_producao (pilha finalizada) caiu dentro
     * de [inicio, fim]. Fichas ainda sem fim_producao (pilha em produção)
     * nunca entram.
     */
    private function fichasNoPeriodo(Apontamento $apontamento, Carbon $inicio, Carbon $fim): Collection
    {
        return $apontamento->fichas->filter(
            fn (FichaApontamento $ficha) => $ficha->fim_producao?->between($inicio, $fim) ?? false
        );
    }

    /**
     * Progresso por cor/variante (cod_peca completo) da peça base (prefixo de
     * 5 dígitos) deste apontamento, usando a quantidade real de peças exigida
     * por cor (não estimativa por pilha). Busca todas as cores esperadas do
     * lote na Bridge — inclusive as que ainda não foram bipadas nenhuma vez —
     * e cruza com o que já foi bipado neste apontamento. Retorna [] quando a
     * Bridge não responde ou não há variantes cadastradas (fallback seguro:
     * não bloqueia finalização por cor nesse caso, só o check agregado).
     */
    public function progressoPorCor(Apontamento $apontamento): array
    {
        $prefixo   = substr($apontamento->cod_peca, 0, 5);
        $variantes = $this->loteService->buscarVariantesPorPrefixoLote($apontamento->ordem_lote, $prefixo);

        if ($variantes === []) {
            return [];
        }

        $bipadoPorCodigo = $apontamento->fichas->groupBy('cod_peca')
            ->map(fn ($fichas) => (int) $fichas->sum('qtd_peca'));

        return array_map(function (array $variante) use ($bipadoPorCodigo) {
            // A cor/acabamento é o último segmento da descrição (ex: "... - Nature").
            $partesDesc = explode(' - ', $variante['desc_peca']);
            $qtdBipada  = (int) ($bipadoPorCodigo[$variante['cod_peca']] ?? 0);

            return [
                'cod_peca'    => $variante['cod_peca'],
                'cor'         => trim((string) end($partesDesc)),
                'qtde_total'  => $variante['qtde_total'],
                'qtd_bipada'  => $qtdBipada,
                'falta'       => max(0, $variante['qtde_total'] - $qtdBipada),
            ];
        }, $variantes);
    }

    /**
     * Alias de leitura de progressoPorCor(), exposto via API para a tela de
     * apontamento. Retorna [] quando há só uma cor (nada a destacar).
     */
    public function resumoFichasPorCor(Apontamento $apontamento): array
    {
        $progresso = $this->progressoPorCor($apontamento);

        return count($progresso) > 1 ? $progresso : [];
    }

    /**
     * Ficha de setup (FichaCabecote) cadastrada para a peça deste apontamento,
     * se houver. Retorna null quando a peça ainda não foi importada localmente
     * do ERP ou quando não existe ficha cadastrada para ela.
     */
    public function buscarFichaSetup(Apontamento $apontamento): ?FichaCabecote
    {
        $peca = $this->produtoPecaLookup->resolver($apontamento->cod_produto, $apontamento->cod_peca);

        if (! $peca) {
            return null;
        }

        return FichaCabecote::with(['maquina', 'operario.user', 'posicoesCabecote', 'posicoesBroca.broca'])
            ->where('produto_peca_id', $peca->id)
            ->first();
    }

    /**
     * Tempo da fase clipado à janela do turno do dia em que a fase iniciou.
     * Se não houver turno configurado para o dia, retorna o valor armazenado
     * no banco como fallback.
     */
    private function tempoNaTurno(Apontamento $apontamento, string $fase): ?int
    {
        $inicio = $fase === 'setup' ? $apontamento->setup_inicio : $apontamento->producao_inicio;

        if (! $inicio) {
            return null;
        }

        $turno = Turno::doDia($inicio->dayOfWeekIso, $inicio);

        if (! $turno) {
            return $fase === 'setup'
                ? $apontamento->setup_duracao_segundos
                : $apontamento->producao_duracao_segundos;
        }

        $janelas = $this->turnoCalculo->janelasUteis($turno, $inicio);
        [$trabalhado] = $this->turnoCalculo->calcularFaseNoDia($apontamento, $fase, $janelas, Carbon::now());

        return $trabalhado;
    }

    /**
     * Duração líquida = (fim - inicio) menos o total de pausas da fase
     * ocorridas dentro da janela atual (inicio >= início da janela).
     * O filtro por janela evita contar duas vezes pausas de uma janela de
     * setup anterior quando o apontamento é forçado a uma nova janela
     * (ver retomarComNovoSetup).
     */
    private function duracaoLiquida(Apontamento $apontamento, string $fase, Carbon $fim): int
    {
        $inicio = $fase === 'setup' ? $apontamento->setup_inicio : $apontamento->producao_inicio;
        $bruto  = (int) $inicio->diffInSeconds($fim);
        $pausas = (int) $apontamento->pausas
            ->where('fase', $fase)
            ->filter(fn ($pausa) => $pausa->inicio->gte($inicio))
            ->sum('duracao_segundos');

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
