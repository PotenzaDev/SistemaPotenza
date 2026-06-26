<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Maquina;
use App\Models\OrdemManutencao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManutencaoQrController extends Controller
{
    use ApiResponseTrait;

    public function maquina(int $id): JsonResponse
    {
        $maquina = Maquina::select('id', 'nome', 'codigo')
            ->findOrFail($id);

        return $this->successResponse($maquina);
    }

    public function solicitar(Request $request, int $maquinaId): JsonResponse
    {
        $maquina = Maquina::findOrFail($maquinaId);

        $data = $request->validate([
            'solicitante' => ['required', 'string', 'max:150'],
            'motivo'      => ['required', 'string'],
            'prioridade'  => ['required', 'in:baixa,normal,alta,critica'],
        ]);

        $ordem = OrdemManutencao::create([
            'maquina_id'   => $maquina->id,
            'solicitante'  => $data['solicitante'],
            'motivo'       => $data['motivo'],
            'prioridade'   => $data['prioridade'],
            'status'       => 'aberta',
            'solicitado_em' => now(),
        ])->load('maquina');

        return $this->successResponse($ordem, 'Solicitação registrada com sucesso.', 201);
    }
}
