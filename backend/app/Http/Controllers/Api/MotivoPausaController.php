<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMotivoPausaRequest;
use App\Http\Requests\UpdateMotivoPausaRequest;
use App\Http\Resources\MotivoPausaResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MotivoPausa;
use Illuminate\Http\JsonResponse;

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
        $motivos = MotivoPausa::orderBy('nome')->get();

        return $this->successResponse(MotivoPausaResource::collection($motivos));
    }

    public function store(StoreMotivoPausaRequest $request): JsonResponse
    {
        $motivo = MotivoPausa::create([
            'nome' => $request->validated('nome'),
            'ativo' => $request->validated('ativo', true),
            'is_sistema' => false,
        ]);

        return $this->successResponse(new MotivoPausaResource($motivo), 'Motivo criado.', 201);
    }

    public function update(UpdateMotivoPausaRequest $request, MotivoPausa $motivos_pausa): JsonResponse
    {
        $motivos_pausa->garantirEditavel('editados');

        $motivos_pausa->update($request->validated());

        return $this->successResponse(new MotivoPausaResource($motivos_pausa->fresh()), 'Motivo atualizado.');
    }

    public function destroy(MotivoPausa $motivos_pausa): JsonResponse
    {
        $motivos_pausa->garantirEditavel('removidos');

        $motivos_pausa->desativar();

        return $this->successResponse(new MotivoPausaResource($motivos_pausa), 'Motivo desativado.');
    }
}
