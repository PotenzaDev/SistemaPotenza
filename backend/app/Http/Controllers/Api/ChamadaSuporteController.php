<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ChamadaSuporte;
use App\Models\SessaoTrabalho;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChamadaSuporteController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request): JsonResponse
    {
        $operario = $request->user()->operario;

        $sessao = SessaoTrabalho::where('operario_id', $operario->id)
            ->where('status', SessaoTrabalho::STATUS_ATIVA)
            ->whereNull('fim')
            ->first();

        if (! $sessao) {
            return $this->errorResponse('Nenhuma sessão ativa encontrada.', 422);
        }

        $chamada = ChamadaSuporte::create([
            'sessao_trabalho_id' => $sessao->id,
            'maquina_id'         => $sessao->maquina_id,
            'operario_id'        => $operario->id,
        ]);

        return $this->successResponse($chamada, 'Suporte solicitado.', 201);
    }

    public function index(): JsonResponse
    {
        $chamadas = ChamadaSuporte::with(['maquina', 'operario'])
            ->whereNull('visualizado_em')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id'        => $c->id,
                'criado_em' => $c->created_at->toISOString(),
                'maquina'   => ['id' => $c->maquina->id, 'nome' => $c->maquina->nome],
                'operario'  => ['id' => $c->operario->id, 'nome' => $c->operario->nome],
            ]);

        return $this->successResponse($chamadas);
    }

    public function visualizar(int $id): JsonResponse
    {
        $chamada = ChamadaSuporte::findOrFail($id);
        $chamada->update(['visualizado_em' => now()]);

        return $this->successResponse(null, 'Chamada dispensada.');
    }
}
