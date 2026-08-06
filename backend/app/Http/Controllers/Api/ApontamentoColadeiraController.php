<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BiparColadeiraRequest;
use App\Http\Requests\BiparFichaColadeiraRequest;
use App\Http\Resources\ApontamentoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Apontamento;
use App\Services\ApontamentoColadeiraService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints exclusivos do fluxo de Coladeira: bipar (inicia setup e grava as
 * dimensões da ficha técnica do lote) e bipar-ficha (grava a ficha com os
 * metros lineares de borda calculados). Pausar, retomar, finalizar-setup,
 * finalizar e finalizar-sem-producao continuam servidos pelas rotas
 * genéricas em /api/apontamento/* (ApontamentoController/ApontamentoService)
 * — ver App\Services\ApontamentoColadeiraService para o porquê.
 */
class ApontamentoColadeiraController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ApontamentoColadeiraService $apontamentoColadeiraService,
    ) {}

    public function bipar(BiparColadeiraRequest $request): JsonResponse
    {
        $apontamento = $this->apontamentoColadeiraService->bipar(
            $request->user()->operario,
            $request->validated()
        );

        return $this->successResponse(
            new ApontamentoResource($apontamento),
            'Lote identificado. Setup iniciado.',
            201
        );
    }

    public function biparFicha(BiparFichaColadeiraRequest $request, Apontamento $apontamento): JsonResponse
    {
        $this->authorize('update', $apontamento);

        $data = $request->validated();

        $result = $this->apontamentoColadeiraService->biparFicha(
            $apontamento,
            $data,
            (bool) ($data['confirmar'] ?? false),
        );

        return $this->successResponse(new ApontamentoResource($result), 'Ficha bipada com sucesso.');
    }
}
