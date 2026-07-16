<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BiparFichaRequest;
use App\Http\Requests\BiparRequest;
use App\Http\Requests\FinalizarApontamentoRequest;
use App\Http\Requests\IniciarSegundaPassagemRequest;
use App\Http\Requests\ListApontamentoDoDiaRequest;
use App\Http\Requests\PausarApontamentoRequest;
use App\Http\Resources\ApontamentoResource;
use App\Http\Resources\FichaApontamentoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Apontamento;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use App\Repositories\Contracts\FichaApontamentoRepositoryInterface;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use App\Services\ApontamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApontamentoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ApontamentoService                  $apontamentoService,
        private readonly ApontamentoRepositoryInterface      $apontamentoRepo,
        private readonly FichaApontamentoRepositoryInterface $fichaRepo,
        private readonly SessaoTrabalhoRepositoryInterface   $sessaoRepo,
    ) {}

    /** Retorna todos os apontamentos ativos da sessão do operário (pode haver mais de um, do mesmo lote). */
    public function ativos(Request $request): JsonResponse
    {
        $sessao = $this->sessaoRepo->buscarSessaoAtiva($request->user()->operario);

        if (! $sessao) {
            return $this->errorResponse('Nenhuma sessão ativa.', 404);
        }

        $apontamentos = $this->apontamentoRepo->buscarApontamentosAtivos($sessao);

        return $this->successResponse(ApontamentoResource::collection($apontamentos), 'Apontamentos ativos.');
    }

    /**
     * Bipar lote — identifica lote e inicia setup automaticamente.
     * Lê apenas cod_peca + ordem_lote do barcode (pilha/qtd_peca ignorados neste passo).
     */
    public function bipar(BiparRequest $request): JsonResponse
    {
        $apontamento = $this->apontamentoService->bipar(
            $request->user()->operario,
            $request->validated()
        );

        return $this->successResponse(
            new ApontamentoResource($apontamento),
            'Lote identificado. Setup iniciado.',
            201
        );
    }

    /** Finaliza o setup → status muda para aguardando_producao. */
    public function finalizarSetup(Apontamento $apontamento): JsonResponse
    {
        $this->authorize('update', $apontamento);

        $result = $this->apontamentoService->finalizarSetup($apontamento);

        return $this->successResponse(new ApontamentoResource($result), 'Setup finalizado. Bipe a primeira ficha.');
    }

    /**
     * Bipar ficha de produção (aguardando_producao ou em_producao).
     * Valida mesmo lote + produto. Primeira ficha inicia o timer de produção.
     */
    public function biparFicha(BiparFichaRequest $request, Apontamento $apontamento): JsonResponse
    {
        $this->authorize('update', $apontamento);

        $data = $request->validated();

        $result = $this->apontamentoService->biparFicha(
            $apontamento,
            $data,
            (bool) ($data['confirmar'] ?? false),
        );

        return $this->successResponse(new ApontamentoResource($result), 'Ficha bipada com sucesso.');
    }

    /**
     * Finaliza a produção com qtd_produzida por ficha.
     * Body: { fichas: [{ficha_id: int, qtd_produzida: int}] }
     */
    public function finalizar(FinalizarApontamentoRequest $request, Apontamento $apontamento): JsonResponse
    {
        $this->authorize('update', $apontamento);

        $result = $this->apontamentoService->finalizar(
            $apontamento,
            $request->validated('fichas'),
            (bool) $request->validated('confirmar_parcial', false),
        );

        return $this->successResponse(
            new ApontamentoResource($result),
            'Apontamento finalizado com sucesso.'
        );
    }

    /** Finaliza sem bipagem individual de fichas (máquinas com possui_producao=false). */
    public function finalizarSemProducao(Apontamento $apontamento): JsonResponse
    {
        $this->authorize('update', $apontamento);

        $result = $this->apontamentoService->finalizarSemProducao($apontamento);

        return $this->successResponse(
            new ApontamentoResource($result),
            'Apontamento finalizado com sucesso.'
        );
    }

    /** Últimas fichas bipadas pelo operário logado no mesmo setor da sessão ativa. */
    public function fichasRecentes(Request $request): JsonResponse
    {
        $operario = $request->user()->operario;
        $sessao   = $this->sessaoRepo->buscarSessaoAtiva($operario);

        if (! $sessao) {
            return $this->successResponse(
                FichaApontamentoResource::collection(collect()),
                'Fichas recentes.'
            );
        }

        $fichas = $this->fichaRepo->fichasRecentesDoOperario(
            $operario->id,
            $sessao->maquina->etapa_fluxo_id,
        );

        return $this->successResponse(
            FichaApontamentoResource::collection($fichas),
            'Fichas recentes.'
        );
    }

    public function show(Request $request, Apontamento $apontamento): JsonResponse
    {
        $this->authorize('view', $apontamento);

        $detalhe = $this->apontamentoService->buscarDetalhe(
            $apontamento,
            $request->only(['data_inicio', 'data_fim']),
        );

        return $this->successResponse(new ApontamentoResource($detalhe));
    }

    /**
     * Resumo das fichas bipadas agrupadas por cor/variante (cod_peca), quando
     * o apontamento tem mais de um código distinto entre as fichas.
     */
    public function fichasPorCor(Apontamento $apontamento): JsonResponse
    {
        $this->authorize('view', $apontamento);

        return $this->successResponse(
            $this->apontamentoService->resumoFichasPorCor($apontamento),
            'Resumo de fichas por cor.'
        );
    }

    /**
     * Ficha de setup (FichaCabecote) cadastrada para a peça deste apontamento,
     * se houver. `data` vem null quando não há peça importada localmente ou
     * quando não existe ficha cadastrada — não é uma condição de erro.
     */
    public function fichaSetup(Apontamento $apontamento): JsonResponse
    {
        $this->authorize('view', $apontamento);

        return $this->successResponse(
            $this->apontamentoService->buscarFichaSetup($apontamento),
            'Ficha de setup.'
        );
    }

    /**
     * Apontamentos para visão gerencial — filtráveis por período, operário,
     * máquina e lote/ordem. Sem filtro de data, retorna os de hoje
     * (iniciados hoje ou ainda em aberto).
     */
    public function doDia(ListApontamentoDoDiaRequest $request): JsonResponse
    {
        $filtros = $request->validated();

        return $this->successResponse(
            $this->apontamentoService->listarApontamentos(array_filter($filtros, fn ($v) => $v !== null)),
            'Apontamentos.'
        );
    }

    public function historico(Request $request): JsonResponse
    {
        $apontamentos = $this->apontamentoRepo->historicoPorOperario(
            $request->user()->operario->id
        );

        return $this->successResponse(
            ApontamentoResource::collection($apontamentos),
            'Histórico de apontamentos.'
        );
    }

    /**
     * Inicia nova passagem do mesmo lote na mesma máquina.
     * Requer que exista apontamento finalizado para o lote/etapa.
     */
    public function iniciarSegundaPassagem(IniciarSegundaPassagemRequest $request): JsonResponse
    {
        $apontamento = $this->apontamentoService->iniciarSegundaPassagem(
            $request->user()->operario,
            $request->validated()
        );

        return $this->successResponse(
            new ApontamentoResource($apontamento),
            'Nova passagem iniciada. Setup começou.',
            201
        );
    }

    /** Pausa manual: operário informa um motivo predefinido. */
    public function pausar(PausarApontamentoRequest $request, Apontamento $apontamento): JsonResponse
    {
        $this->authorize('update', $apontamento);

        $result = $this->apontamentoService->pausar($apontamento, $request->validated('motivo_pausa_id'));

        return $this->successResponse(new ApontamentoResource($result), 'Apontamento pausado.');
    }

    /**
     * Auto-pausa de sistema: chamada via sendBeacon ao fechar o navegador.
     * Usa o motivo is_sistema=true; não requer body.
     */
    public function pausarSistema(Apontamento $apontamento): JsonResponse
    {
        $this->authorize('update', $apontamento);

        $result = $this->apontamentoService->pausarSistema($apontamento);

        return $this->successResponse(new ApontamentoResource($result), 'Auto-pausa registrada.');
    }

    /** Retoma um apontamento pausado. */
    public function retomar(Apontamento $apontamento): JsonResponse
    {
        $this->authorize('update', $apontamento);

        $result = $this->apontamentoService->retomar($apontamento);

        return $this->successResponse(new ApontamentoResource($result), 'Apontamento retomado.');
    }
}
