<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuscarFichaTecnicaLoteRequest;
use App\Http\Requests\BuscarFtecPecaPilhaRequest;
use App\Services\FichaTecnica\FichaTecnicaServiceInterface;
use Illuminate\Http\JsonResponse;

class FichaTecnicaController extends Controller
{
    public function __construct(
        private readonly FichaTecnicaServiceInterface $fichaTecnicaService,
    ) {}

    public function lote(BuscarFichaTecnicaLoteRequest $request): JsonResponse
    {
        $dados = $this->fichaTecnicaService->buscarPorOrdemLote(
            $request->validated('lote'),
            $request->validated('cod_peca'),
        );

        return response()->json($dados);
    }

    public function pilha(BuscarFtecPecaPilhaRequest $request): JsonResponse
    {
        $valor = $this->fichaTecnicaService->buscarFtecPecaPilha(
            $request->validated('cod_peca'),
        );

        return response()->json(['ftec_peca_pilha' => $valor]);
    }
}
