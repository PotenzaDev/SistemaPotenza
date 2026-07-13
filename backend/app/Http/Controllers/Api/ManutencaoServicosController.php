<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServicoOrdemManutencaoRequest;
use App\Http\Resources\OrdemManutencaoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OrdemManutencao;
use App\Services\ManutencaoService;
use Illuminate\Http\JsonResponse;

class ManutencaoServicosController extends Controller
{
    use ApiResponseTrait;

    private const RELACOES = ['maquina.etapaFluxo', 'pecas', 'servicos'];

    public function __construct(private readonly ManutencaoService $manutencaoService)
    {
    }

    public function store(StoreServicoOrdemManutencaoRequest $request, OrdemManutencao $ordemId): JsonResponse
    {
        $ordem = $this->manutencaoService->adicionarServico($ordemId, $request->validated());

        return $this->successResponse(
            new OrdemManutencaoResource($ordem->load(self::RELACOES)),
            'Serviço adicionado.',
            201
        );
    }

    public function destroy(OrdemManutencao $ordemId, int $servicoId): JsonResponse
    {
        $ordem = $this->manutencaoService->removerServico($ordemId, $servicoId);

        return $this->successResponse(
            new OrdemManutencaoResource($ordem->load(self::RELACOES)),
            'Serviço removido.'
        );
    }
}
