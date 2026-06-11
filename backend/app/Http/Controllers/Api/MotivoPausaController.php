<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MotivoPausa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotivoPausaController extends Controller
{
    use ApiResponseTrait;

    /** Lista motivos ativos para selecao pelo operario (exclui motivos de sistema). */
    public function indexOperario(): JsonResponse
    {
        return $this->successResponse(
            MotivoPausa::operario()->orderBy('nome')->get(['id', 'nome'])
        );
    }

    /** Lista todos os motivos para o admin (inclui inativos e de sistema). */
    public function index(): JsonResponse
    {
        return $this->successResponse(
            MotivoPausa::orderBy('nome')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome'  => ['required', 'string', 'max:100', 'unique:motivos_pausa,nome'],
            'ativo' => ['boolean'],
        ]);

        $motivo = MotivoPausa::create([
            'nome'       => $data['nome'],
            'ativo'      => $data['ativo'] ?? true,
            'is_sistema' => false,
        ]);

        return $this->successResponse($motivo, 'Motivo criado.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $motivo = MotivoPausa::find($id);

        if (! $motivo) {
            return $this->errorResponse('Motivo nao encontrado.', 404);
        }

        if ($motivo->is_sistema) {
            return $this->errorResponse('Motivos de sistema nao podem ser editados.', 403);
        }

        $data = $request->validate([
            'nome'  => ['sometimes', 'string', 'max:100', 'unique:motivos_pausa,nome,' . $id],
            'ativo' => ['boolean'],
        ]);

        $motivo->update($data);

        return $this->successResponse($motivo, 'Motivo atualizado.');
    }

    public function destroy(int $id): JsonResponse
    {
        $motivo = MotivoPausa::find($id);

        if (! $motivo) {
            return $this->errorResponse('Motivo nao encontrado.', 404);
        }

        if ($motivo->is_sistema) {
            return $this->errorResponse('Motivos de sistema nao podem ser removidos.', 403);
        }

        $motivo->update(['ativo' => false]);

        return $this->successResponse($motivo, 'Motivo desativado.');
    }
}
