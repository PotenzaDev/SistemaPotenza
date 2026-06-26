<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OrdemManutencao;
use App\Models\ServicoOrdemManutencao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManutencaoServicosController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request, int $ordemId): JsonResponse
    {
        $ordem = OrdemManutencao::find($ordemId);

        if (! $ordem) {
            return $this->errorResponse('OS não encontrada.', 404);
        }

        $data = $request->validate([
            'servico'   => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
            'valor'     => ['required', 'numeric', 'min:0'],
            'data'      => ['required', 'date'],
        ]);

        ServicoOrdemManutencao::create([
            'ordem_manutencao_id' => $ordemId,
            ...$data,
        ]);

        return $this->successResponse(
            $ordem->load(['maquina.etapaFluxo', 'pecas', 'servicos']),
            'Serviço adicionado.',
            201
        );
    }

    public function destroy(int $ordemId, int $servicoId): JsonResponse
    {
        $ordem = OrdemManutencao::find($ordemId);

        if (! $ordem) {
            return $this->errorResponse('OS não encontrada.', 404);
        }

        $servico = ServicoOrdemManutencao::where('ordem_manutencao_id', $ordemId)->find($servicoId);

        if (! $servico) {
            return $this->errorResponse('Serviço não encontrado.', 404);
        }

        $servico->delete();

        return $this->successResponse(
            $ordem->load(['maquina.etapaFluxo', 'pecas', 'servicos']),
            'Serviço removido.'
        );
    }
}
