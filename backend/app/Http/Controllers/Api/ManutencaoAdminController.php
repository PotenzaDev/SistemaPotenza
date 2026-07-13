<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListManutencaoRequest;
use App\Http\Requests\SolicitarManutencaoRequest;
use App\Http\Requests\UpdateOrdemManutencaoRequest;
use App\Http\Resources\OrdemManutencaoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OrdemManutencao;
use App\Services\ManutencaoService;
use Illuminate\Http\JsonResponse;

class ManutencaoAdminController extends Controller
{
    use ApiResponseTrait;

    private const RELACOES = ['maquina.etapaFluxo', 'pecas', 'servicos'];

    public function __construct(private readonly ManutencaoService $manutencaoService)
    {
    }

    public function index(ListManutencaoRequest $request): JsonResponse
    {
        $ordens = $this->manutencaoService->listar($request->validated());

        return $this->successResponse(OrdemManutencaoResource::collection($ordens));
    }

    public function store(SolicitarManutencaoRequest $request): JsonResponse
    {
        $ordem = $this->manutencaoService->criarSolicitacao($request->validated());

        return $this->successResponse(
            new OrdemManutencaoResource($ordem->load(self::RELACOES)),
            'OS criada.',
            201
        );
    }

    public function show(OrdemManutencao $id): JsonResponse
    {
        return $this->successResponse(new OrdemManutencaoResource($id->load(self::RELACOES)));
    }

    public function update(UpdateOrdemManutencaoRequest $request, OrdemManutencao $id): JsonResponse
    {
        $ordem = $this->manutencaoService->atualizarStatus($id, $request->validated());

        return $this->successResponse(new OrdemManutencaoResource($ordem->load(self::RELACOES)), 'OS atualizada.');
    }
}
