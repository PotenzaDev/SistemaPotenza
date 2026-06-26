<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OrdemManutencao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManutencaoPublicoController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $ordens = OrdemManutencao::with('maquina.etapaFluxo')
            ->where('status', 'aberta')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse($ordens);
    }

    public function solicitar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'maquina_id'  => ['required', 'integer', 'exists:maquinas,id'],
            'solicitante' => ['required', 'string', 'max:150'],
            'motivo'      => ['required', 'string'],
            'prioridade'  => ['required', 'in:baixa,normal,alta,critica'],
        ]);

        $data['status']        = 'aberta';
        $data['solicitado_em'] = now();

        return $this->successResponse(
            OrdemManutencao::create($data)->load('maquina.etapaFluxo'),
            'Solicitação de manutenção registrada.',
            201
        );
    }
}
