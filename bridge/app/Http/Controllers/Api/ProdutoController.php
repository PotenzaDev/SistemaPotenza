<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuscarPecasProdutoRequest;
use App\Http\Requests\ListarProdutosRequest;
use App\Http\Requests\ListarSubGruposRequest;
use App\Services\Produto\ProdutoServiceInterface;
use Illuminate\Http\JsonResponse;

class ProdutoController extends Controller
{
    public function __construct(
        private readonly ProdutoServiceInterface $produtoService,
    ) {}

    public function index(ListarProdutosRequest $request): JsonResponse
    {
        $dados = $this->produtoService->listar(
            $request->validated('empresa'),
            $request->validated('nome'),
            $request->validated('sub_grupo'),
            $request->validated('data_corte'),
        );

        return response()->json($dados);
    }

    public function subGrupos(ListarSubGruposRequest $request): JsonResponse
    {
        $dados = $this->produtoService->listarSubGrupos(
            $request->validated('empresa'),
            $request->validated('data_corte'),
        );

        return response()->json($dados);
    }

    public function pecas(BuscarPecasProdutoRequest $request, string $codProduto): JsonResponse
    {
        $dados = $this->produtoService->buscarPecas($codProduto);

        return response()->json($dados);
    }
}
