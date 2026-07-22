<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BiparCorteRequest;
use App\Http\Requests\FinalizarApontamentoCorteRequest;
use App\Http\Resources\ApontamentoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Apontamento;
use App\Services\ApontamentoCorteService;
use Illuminate\Http\JsonResponse;

class ApontamentoCorteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ApontamentoCorteService $apontamentoCorteService,
    ) {}

    /**
     * Bipar no fluxo de corte (por lote). Sem apontamento ativo do lote, cria
     * um novo já em produção (sem setup) e já registra a primeira ficha; com
     * um já ativo, apenas acrescenta a ficha bipada a ele.
     */
    public function bipar(BiparCorteRequest $request): JsonResponse
    {
        $apontamento = $this->apontamentoCorteService->bipar(
            $request->user()->operario,
            $request->validated()
        );

        return $this->successResponse(
            new ApontamentoResource($apontamento),
            'Ficha bipada com sucesso.',
            201
        );
    }

    /** Checklist do lote inteiro: todas as peças esperadas e o que já foi bipado. */
    public function checklist(Apontamento $apontamento): JsonResponse
    {
        $this->authorize('view', $apontamento);

        return $this->successResponse(
            $this->apontamentoCorteService->checklistDoLote($apontamento),
            'Checklist do lote.'
        );
    }

    /**
     * Finaliza a produção com qtd_produzida por ficha.
     * Body: { fichas: [{ficha_id: int, qtd_produzida: int}] }
     */
    public function finalizar(FinalizarApontamentoCorteRequest $request, Apontamento $apontamento): JsonResponse
    {
        $this->authorize('update', $apontamento);

        $result = $this->apontamentoCorteService->finalizar(
            $apontamento,
            $request->validated('fichas'),
            (bool) $request->validated('confirmar_parcial', false),
        );

        return $this->successResponse(
            new ApontamentoResource($result),
            'Apontamento finalizado com sucesso.'
        );
    }
}
