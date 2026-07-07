<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuscarFichaTecnicaLoteRequest;
use App\Http\Requests\BuscarFichasLoteCountRequest;
use App\Http\Requests\BuscarFtecPecaPilhaRequest;
use App\Http\Requests\BuscarTotaisVariantesLoteRequest;
use App\Http\Requests\BuscarVariantesDetalheLoteRequest;
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

    public function countFichas(BuscarFichasLoteCountRequest $request): JsonResponse
    {
        $total = $this->fichaTecnicaService->contarFichasLote(
            $request->validated('lote'),
            $request->validated('cod_peca'),
        );

        return response()->json(['total' => $total]);
    }

    public function loteVariantes(BuscarTotaisVariantesLoteRequest $request): JsonResponse
    {
        $totais = $this->fichaTecnicaService->buscarTotaisPorPrefixoLote(
            $request->validated('lote'),
            $request->validated('prefixo_cod'),
        );

        return response()->json($totais);
    }

    public function loteVariantesDetalhe(BuscarVariantesDetalheLoteRequest $request): JsonResponse
    {
        $variantes = $this->fichaTecnicaService->buscarVariantesPorPrefixoLote(
            $request->validated('lote'),
            $request->validated('prefixo_cod'),
        );

        return response()->json(['variantes' => $variantes]);
    }
}
