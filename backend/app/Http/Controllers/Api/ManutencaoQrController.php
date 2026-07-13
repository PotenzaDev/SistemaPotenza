<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitarManutencaoQrRequest;
use App\Http\Resources\MaquinaPublicaResource;
use App\Http\Resources\OrdemManutencaoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Maquina;
use App\Services\ManutencaoService;
use Illuminate\Http\JsonResponse;

class ManutencaoQrController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ManutencaoService $manutencaoService)
    {
    }

    public function maquina(Maquina $id): JsonResponse
    {
        return $this->successResponse(new MaquinaPublicaResource($id));
    }

    public function solicitar(SolicitarManutencaoQrRequest $request, Maquina $maquinaId): JsonResponse
    {
        $ordem = $this->manutencaoService->criarSolicitacao([
            ...$request->validated(),
            'maquina_id' => $maquinaId->id,
        ]);

        return $this->successResponse(
            new OrdemManutencaoResource($ordem->load(['maquina.etapaFluxo', 'pecas', 'servicos'])),
            'Solicitação registrada com sucesso.',
            201
        );
    }
}
