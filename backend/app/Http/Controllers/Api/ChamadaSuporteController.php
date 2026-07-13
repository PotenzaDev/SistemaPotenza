<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChamadaSuporteResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ChamadaSuporte;
use App\Services\ChamadaSuporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChamadaSuporteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ChamadaSuporteService $chamadaSuporteService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $chamada = $this->chamadaSuporteService->solicitar($request->user()->operario);

        return $this->successResponse(new ChamadaSuporteResource($chamada), 'Suporte solicitado.', 201);
    }

    public function storeManutencao(): JsonResponse
    {
        $chamada = $this->chamadaSuporteService->solicitarManutencao();

        return $this->successResponse(new ChamadaSuporteResource($chamada), 'Suporte solicitado pela manutenção.', 201);
    }

    public function index(): JsonResponse
    {
        $chamadas = $this->chamadaSuporteService->listarPendentes();

        return $this->successResponse(ChamadaSuporteResource::collection($chamadas));
    }

    public function visualizar(ChamadaSuporte $chamada_suporte): JsonResponse
    {
        $this->chamadaSuporteService->marcarVisualizada($chamada_suporte);

        return $this->successResponse(null, 'Chamada dispensada.');
    }
}
