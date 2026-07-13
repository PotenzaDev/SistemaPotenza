<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEtapaFluxoRequest;
use App\Http\Requests\UpdateEtapaFluxoRequest;
use App\Http\Resources\EtapaFluxoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\EtapaFluxo;
use Illuminate\Http\JsonResponse;

class EtapaFluxoController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $etapas = EtapaFluxo::orderBy('ordem')->get();

        return $this->successResponse(EtapaFluxoResource::collection($etapas));
    }

    public function store(StoreEtapaFluxoRequest $request): JsonResponse
    {
        $etapa = EtapaFluxo::create($request->validated());

        return $this->successResponse(new EtapaFluxoResource($etapa), 'Etapa criada.', 201);
    }

    public function show(EtapaFluxo $etapas_fluxo): JsonResponse
    {
        return $this->successResponse(new EtapaFluxoResource($etapas_fluxo));
    }

    public function update(UpdateEtapaFluxoRequest $request, EtapaFluxo $etapas_fluxo): JsonResponse
    {
        $etapas_fluxo->update($request->validated());

        return $this->successResponse(new EtapaFluxoResource($etapas_fluxo), 'Etapa atualizada.');
    }

    public function destroy(EtapaFluxo $etapas_fluxo): JsonResponse
    {
        $etapas_fluxo->delete();

        return $this->successResponse(null, 'Etapa removida.');
    }
}
