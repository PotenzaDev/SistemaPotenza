<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Broca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrocaController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        return $this->successResponse(Broca::orderBy('codigo')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'unique:brocas,codigo'],
            'espessura_mm' => ['required', 'numeric', 'min:0.01'],
            'rotacao' => ['required', 'string', 'in:direita,esquerda'],
            'altura_mm' => ['required', 'numeric', 'min:0.01'],
            'furo_passante' => ['required', 'boolean'],
            'ativo' => ['boolean'],
        ]);

        $broca = Broca::create($data);

        return $this->successResponse($broca, 'Broca criada.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $broca = Broca::find($id);

        if (! $broca) {
            return $this->errorResponse('Broca não encontrada.', 404);
        }

        $data = $request->validate([
            'codigo' => ['sometimes', 'string', 'max:50', 'unique:brocas,codigo,'.$id],
            'espessura_mm' => ['sometimes', 'numeric', 'min:0.01'],
            'rotacao' => ['sometimes', 'string', 'in:direita,esquerda'],
            'altura_mm' => ['sometimes', 'numeric', 'min:0.01'],
            'furo_passante' => ['sometimes', 'boolean'],
            'ativo' => ['boolean'],
        ]);

        $broca->update($data);

        return $this->successResponse($broca, 'Broca atualizada.');
    }

    public function destroy(int $id): JsonResponse
    {
        $broca = Broca::find($id);

        if (! $broca) {
            return $this->errorResponse('Broca não encontrada.', 404);
        }

        $broca->update(['ativo' => false]);

        return $this->successResponse($broca, 'Broca desativada.');
    }
}
