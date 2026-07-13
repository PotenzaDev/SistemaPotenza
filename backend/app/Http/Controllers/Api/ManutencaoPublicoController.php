<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitarManutencaoRequest;
use App\Http\Resources\OrdemManutencaoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OrdemManutencao;
use App\Services\ManutencaoService;
use Illuminate\Http\JsonResponse;

class ManutencaoPublicoController extends Controller
{
    use ApiResponseTrait;

    private const RELACOES = ['maquina.etapaFluxo', 'pecas', 'servicos'];

    public function __construct(private readonly ManutencaoService $manutencaoService)
    {
    }

    public function index(): JsonResponse
    {
        $ordens = OrdemManutencao::with(self::RELACOES)
            ->where('status', 'aberta')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(OrdemManutencaoResource::collection($ordens));
    }

    public function solicitar(SolicitarManutencaoRequest $request): JsonResponse
    {
        $ordem = $this->manutencaoService->criarSolicitacao($request->validated());

        return $this->successResponse(
            new OrdemManutencaoResource($ordem->load(self::RELACOES)),
            'Solicitação de manutenção registrada.',
            201
        );
    }
}
