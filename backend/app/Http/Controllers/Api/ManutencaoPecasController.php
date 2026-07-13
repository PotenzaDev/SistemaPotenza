<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePecaOrdemManutencaoRequest;
use App\Http\Resources\OrdemManutencaoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OrdemManutencao;
use App\Services\ManutencaoService;
use Illuminate\Http\JsonResponse;

class ManutencaoPecasController extends Controller
{
    use ApiResponseTrait;

    private const RELACOES = ['maquina.etapaFluxo', 'pecas', 'servicos'];

    public function __construct(private readonly ManutencaoService $manutencaoService)
    {
    }

    public function store(StorePecaOrdemManutencaoRequest $request, OrdemManutencao $ordemId): JsonResponse
    {
        $ordem = $this->manutencaoService->adicionarPeca($ordemId, $request->validated());

        return $this->successResponse(
            new OrdemManutencaoResource($ordem->load(self::RELACOES)),
            'Peça adicionada.',
            201
        );
    }

    public function destroy(OrdemManutencao $ordemId, int $pecaId): JsonResponse
    {
        $ordem = $this->manutencaoService->removerPeca($ordemId, $pecaId);

        return $this->successResponse(
            new OrdemManutencaoResource($ordem->load(self::RELACOES)),
            'Peça removida.'
        );
    }
}
