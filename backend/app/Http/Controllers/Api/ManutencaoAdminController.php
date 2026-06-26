<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OrdemManutencao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManutencaoAdminController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $query = OrdemManutencao::with(['maquina.etapaFluxo', 'pecas', 'servicos']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('data')) {
            $query->whereDate('solicitado_em', $request->data);
        }
        if ($request->filled('etapa_fluxo_id')) {
            $query->whereHas('maquina', fn ($q) => $q->where('etapa_fluxo_id', $request->etapa_fluxo_id));
        }

        return $this->successResponse($query->orderByDesc('solicitado_em')->get());
    }

    public function store(Request $request): JsonResponse
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
            OrdemManutencao::create($data)->load(['maquina.etapaFluxo', 'pecas', 'servicos']),
            'OS criada.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $ordem = OrdemManutencao::with(['maquina.etapaFluxo', 'pecas', 'servicos'])->find($id);

        return $ordem
            ? $this->successResponse($ordem)
            : $this->errorResponse('OS não encontrada.', 404);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ordem = OrdemManutencao::find($id);

        if (! $ordem) {
            return $this->errorResponse('OS não encontrada.', 404);
        }

        $data = $request->validate([
            'status'      => ['sometimes', 'in:aberta,em_atendimento,pausada,concluida,cancelada'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ]);

        if (isset($data['status'])) {
            if ($data['status'] === 'em_atendimento' && is_null($ordem->atendido_em)) {
                $data['atendido_em'] = now();
            }
            if ($data['status'] === 'concluida') {
                $data['concluido_em'] = now();
            }
        }

        $ordem->update($data);

        return $this->successResponse($ordem->load(['maquina.etapaFluxo', 'pecas', 'servicos']), 'OS atualizada.');
    }
}
