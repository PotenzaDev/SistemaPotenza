<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApontamentoResource;
use App\Http\Resources\FichaApontamentoResource;
use App\Http\Traits\ApiResponseTrait;
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

    /** Retorna o apontamento ativo do operário (se houver). */
    public function ativo(Request $request): JsonResponse
    {
        $sessao = $this->sessaoRepo->buscarSessaoAtiva($request->user()->operario);

        if (! $sessao) {
            return $this->errorResponse('Nenhuma sessão ativa.', 404);
        }

        $apontamento = $this->apontamentoRepo->buscarApontamentoAtivo($sessao);

        if (! $apontamento) {
            return $this->errorResponse('Nenhum apontamento ativo.', 404);
        }

        return $this->successResponse(new ApontamentoResource($apontamento), 'Apontamento ativo.');
    }

    /**
     * Bipar lote — identifica lote e inicia setup automaticamente.
     * Lê apenas cod_peca + ordem_lote do barcode (pilha/qtd_peca ignorados neste passo).
     */
    public function bipar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cod_peca'   => ['required', 'string'],
            'ordem_lote' => ['required', 'string'],
        ]);

        $apontamento = $this->apontamentoService->bipar(
            $request->user()->operario,
            $data
        );

        return $this->successResponse(
            new ApontamentoResource($apontamento),
            'Lote identificado. Setup iniciado.',
            201
        );
    }

    /** Finaliza o setup → status muda para aguardando_producao. */
    public function finalizarSetup(Request $request, int $id): JsonResponse
    {
        $apontamento = $this->apontamentoRepo->buscarPorId($id);

        if (! $apontamento) {
            return $this->errorResponse('Apontamento não encontrado.', 404);
        }

        $this->authorize('update', $apontamento);

        $result = $this->apontamentoService->finalizarSetup($apontamento);

        return $this->successResponse(new ApontamentoResource($result), 'Setup finalizado. Bipe a primeira ficha.');
    }

    /**
     * Bipar ficha de produção (aguardando_producao ou em_producao).
     * Valida mesmo lote + produto. Primeira ficha inicia o timer de produção.
     */
    public function biparFicha(Request $request, int $id): JsonResponse
    {
        $apontamento = $this->apontamentoRepo->buscarPorId($id);

        if (! $apontamento) {
            return $this->errorResponse('Apontamento não encontrado.', 404);
        }

        $this->authorize('update', $apontamento);

        $data = $request->validate([
            'cod_peca'   => ['required', 'string'],
            'ordem_lote' => ['required', 'string'],
            'qtd_peca'   => ['required', 'integer', 'min:1'],
            'pilha'      => ['required', 'integer', 'min:1'],
            'confirmar'  => ['sometimes', 'boolean'],
        ]);

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
    public function finalizar(Request $request, int $id): JsonResponse
    {
        $apontamento = $this->apontamentoRepo->buscarPorId($id);

        if (! $apontamento) {
            return $this->errorResponse('Apontamento não encontrado.', 404);
        }

        $this->authorize('update', $apontamento);

        $data = $request->validate([
            'fichas'                 => ['required', 'array', 'min:1'],
            'fichas.*.ficha_id'      => ['required', 'integer'],
            'fichas.*.qtd_produzida' => ['required', 'integer', 'min:0'],
        ]);

        $result = $this->apontamentoService->finalizar($apontamento, $data['fichas']);

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

    public function show(Request $request, int $id): JsonResponse
    {
        $apontamento = $this->apontamentoService->buscarDetalhe(
            $id,
            $request->only(['data_inicio', 'data_fim']),
        );

        if (! $apontamento) {
            return $this->errorResponse('Apontamento não encontrado.', 404);
        }

        $this->authorize('view', $apontamento);

        return $this->successResponse(new ApontamentoResource($apontamento));
    }

    /**
     * Resumo das fichas bipadas agrupadas por cor/variante (cod_peca), quando
     * o apontamento tem mais de um código distinto entre as fichas.
     */
    public function fichasPorCor(Request $request, int $id): JsonResponse
    {
        $apontamento = $this->apontamentoRepo->buscarPorId($id);

        if (! $apontamento) {
            return $this->errorResponse('Apontamento não encontrado.', 404);
        }

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
    public function fichaSetup(Request $request, int $id): JsonResponse
    {
        $apontamento = $this->apontamentoRepo->buscarPorId($id);

        if (! $apontamento) {
            return $this->errorResponse('Apontamento não encontrado.', 404);
        }

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
    public function doDia(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim'    => ['nullable', 'date_format:Y-m-d'],
            'operario_id' => ['nullable', 'integer', 'exists:operarios,id'],
            'maquina_id'  => ['nullable', 'integer', 'exists:maquinas,id'],
            'ordem_lote'  => ['nullable', 'string'],
        ]);

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
    public function iniciarSegundaPassagem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cod_peca'   => ['required', 'string'],
            'ordem_lote' => ['required', 'string'],
        ]);

        $apontamento = $this->apontamentoService->iniciarSegundaPassagem(
            $request->user()->operario,
            $data
        );

        return $this->successResponse(
            new ApontamentoResource($apontamento),
            'Nova passagem iniciada. Setup começou.',
            201
        );
    }

    /** Pausa manual: operário informa um motivo predefinido. */
    public function pausar(Request $request, int $id): JsonResponse
    {
        $apontamento = $this->apontamentoRepo->buscarPorId($id);

        if (! $apontamento) {
            return $this->errorResponse('Apontamento não encontrado.', 404);
        }

        $this->authorize('update', $apontamento);

        $data = $request->validate([
            'motivo_pausa_id' => ['required', 'integer', 'exists:motivos_pausa,id'],
        ]);

        $result = $this->apontamentoService->pausar($apontamento, $data['motivo_pausa_id']);

        return $this->successResponse(new ApontamentoResource($result), 'Apontamento pausado.');
    }

    /**
     * Auto-pausa de sistema: chamada via sendBeacon ao fechar o navegador.
     * Usa o motivo is_sistema=true; não requer body.
     */
    public function pausarSistema(Request $request, int $id): JsonResponse
    {
        $apontamento = $this->apontamentoRepo->buscarPorId($id);

        if (! $apontamento) {
            return $this->errorResponse('Apontamento não encontrado.', 404);
        }

        $this->authorize('update', $apontamento);

        // Se já pausado, ignora silenciosamente
        if (in_array($apontamento->status, [
            \App\Models\Apontamento::STATUS_EM_PAUSA_SETUP,
            \App\Models\Apontamento::STATUS_EM_PAUSA_PRODUCAO,
        ], true)) {
            return $this->successResponse(new ApontamentoResource($apontamento), 'Já pausado.');
        }

        $motivoSistema = \App\Models\MotivoPausa::where('is_sistema', true)->first();

        if (! $motivoSistema) {
            return $this->errorResponse('Motivo de sistema não configurado.', 500);
        }

        $result = $this->apontamentoService->pausar($apontamento, $motivoSistema->id, true);

        return $this->successResponse(new ApontamentoResource($result), 'Auto-pausa registrada.');
    }

    /** Retoma um apontamento pausado. */
    public function retomar(Request $request, int $id): JsonResponse
    {
        $apontamento = $this->apontamentoRepo->buscarPorId($id);

        if (! $apontamento) {
            return $this->errorResponse('Apontamento não encontrado.', 404);
        }

        $this->authorize('update', $apontamento);

        $result = $this->apontamentoService->retomar($apontamento);

        return $this->successResponse(new ApontamentoResource($result), 'Apontamento retomado.');
    }
}
