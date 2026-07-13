<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRotinaRequest;
use App\Http\Requests\UpdateRotinaRequest;
use App\Http\Resources\RotinaResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Rotina;
use Illuminate\Http\JsonResponse;

class RotinaController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $rotinas = Rotina::topLevel()->orderBy('ordem')->with('filhos')->get();

        return $this->successResponse(RotinaResource::collection($rotinas));
    }

    public function menu(): JsonResponse
    {
        $rotinas = Rotina::topLevel()
            ->ativa()
            ->orderBy('ordem')
            ->with(['filhos' => fn ($query) => $query->ativa()])
            ->get();

        return $this->successResponse(RotinaResource::collection($rotinas));
    }

    public function store(CreateRotinaRequest $request): JsonResponse
    {
        $rotina = Rotina::create($request->validated());

        return $this->successResponse(new RotinaResource($rotina), 'Rotina cadastrada.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $rotina = Rotina::with('filhos')->find($id);

        return $rotina
            ? $this->successResponse(new RotinaResource($rotina))
            : $this->errorResponse('Rotina não encontrada.', 404);
    }

    public function update(UpdateRotinaRequest $request, int $id): JsonResponse
    {
        $rotina = Rotina::find($id);

        if (! $rotina) {
            return $this->errorResponse('Rotina não encontrada.', 404);
        }

        $rotina->update($request->validated());

        return $this->successResponse(new RotinaResource($rotina->fresh()), 'Rotina atualizada.');
    }

    public function destroy(int $id): JsonResponse
    {
        $rotina = Rotina::find($id);

        if (! $rotina) {
            return $this->errorResponse('Rotina não encontrada.', 404);
        }

        $rotina->delete();

        return $this->successResponse(null, 'Rotina removida.');
    }
}
