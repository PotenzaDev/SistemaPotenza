<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrocaRequest;
use App\Http\Requests\UpdateBrocaRequest;
use App\Http\Resources\BrocaResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Broca;
use Illuminate\Http\JsonResponse;

class BrocaController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $brocas = Broca::orderBy('codigo')->get();

        return $this->successResponse(BrocaResource::collection($brocas));
    }

    public function store(StoreBrocaRequest $request): JsonResponse
    {
        $broca = Broca::create($request->validated());

        return $this->successResponse(new BrocaResource($broca), 'Broca criada.', 201);
    }

    public function update(UpdateBrocaRequest $request, Broca $broca): JsonResponse
    {
        $broca->update($request->validated());

        return $this->successResponse(new BrocaResource($broca->fresh()), 'Broca atualizada.');
    }

    public function destroy(Broca $broca): JsonResponse
    {
        $broca->desativar();

        return $this->successResponse(new BrocaResource($broca), 'Broca desativada.');
    }
}
