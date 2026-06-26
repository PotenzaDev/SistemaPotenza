<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OrdemManutencao;
use App\Models\PecaOrdemManutencao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManutencaoPecasController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request, int $ordemId): JsonResponse
    {
        $ordem = OrdemManutencao::find($ordemId);

        if (! $ordem) {
            return $this->errorResponse('OS não encontrada.', 404);
        }

        $data = $request->validate([
            'descricao'      => ['required', 'string', 'max:200'],
            'quantidade'     => ['required', 'numeric', 'min:0.001'],
            'preco_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        PecaOrdemManutencao::create([
            'ordem_manutencao_id' => $ordemId,
            ...$data,
        ]);

        return $this->successResponse(
            $ordem->load(['maquina.etapaFluxo', 'pecas', 'servicos']),
            'Peça adicionada.',
            201
        );
    }

    public function destroy(int $ordemId, int $pecaId): JsonResponse
    {
        $ordem = OrdemManutencao::find($ordemId);

        if (! $ordem) {
            return $this->errorResponse('OS não encontrada.', 404);
        }

        $peca = PecaOrdemManutencao::where('ordem_manutencao_id', $ordemId)->find($pecaId);

        if (! $peca) {
            return $this->errorResponse('Peça não encontrada.', 404);
        }

        $peca->delete();

        return $this->successResponse(
            $ordem->load(['maquina.etapaFluxo', 'pecas', 'servicos']),
            'Peça removida.'
        );
    }
}
