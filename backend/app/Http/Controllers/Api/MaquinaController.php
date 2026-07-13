<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaquinaRequest;
use App\Http\Requests\UpdateMaquinaRequest;
use App\Http\Resources\MaquinaResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Maquina;
use App\Services\MaquinaService;
use Illuminate\Http\JsonResponse;

class MaquinaController extends Controller
{
    use ApiResponseTrait;

    private const RELACOES = ['etapaFluxo', 'configuracaoCabecote', 'regraMaquina'];

    public function __construct(private readonly MaquinaService $maquinaService)
    {
    }

    public function index(): JsonResponse
    {
        $maquinas = Maquina::with(self::RELACOES)->orderBy('nome')->get();

        return $this->successResponse(MaquinaResource::collection($maquinas));
    }

    public function store(StoreMaquinaRequest $request): JsonResponse
    {
        $maquina = $this->maquinaService->criar($request->validated(), $request->file('foto'));

        return $this->successResponse(
            new MaquinaResource($maquina->load(self::RELACOES)),
            'Máquina criada.',
            201
        );
    }

    public function show(Maquina $maquina): JsonResponse
    {
        return $this->successResponse(new MaquinaResource($maquina->load(self::RELACOES)));
    }

    public function update(UpdateMaquinaRequest $request, Maquina $maquina): JsonResponse
    {
        $maquina = $this->maquinaService->atualizar($maquina, $request->validated(), $request->file('foto'));

        return $this->successResponse(new MaquinaResource($maquina->load(self::RELACOES)), 'Máquina atualizada.');
    }

    public function destroy(Maquina $maquina): JsonResponse
    {
        $maquina->desativar();

        return $this->successResponse(new MaquinaResource($maquina->load(self::RELACOES)), 'Máquina desativada.');
    }
}
