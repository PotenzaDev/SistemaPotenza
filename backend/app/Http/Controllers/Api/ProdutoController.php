<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuscarPecaPorCodigoRequest;
use App\Http\Requests\ImportarProdutoRequest;
use App\Http\Requests\ListProdutoErpRequest;
use App\Http\Requests\ListSubGruposErpRequest;
use App\Http\Resources\ProdutoPecaResource;
use App\Http\Resources\ProdutoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Services\Produto\ProdutoImportServiceInterface;
use App\Services\Produto\ProdutoService;
use Illuminate\Http\JsonResponse;

class ProdutoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ProdutoImportServiceInterface $produtoImportService,
        private readonly ProdutoService $produtoService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(ProdutoResource::collection($this->produtoService->listar()));
    }

    public function show(int $id): JsonResponse
    {
        $produto = $this->produtoService->buscarComPecas($id);

        if (! $produto) {
            return $this->errorResponse('Produto não encontrado.', 404);
        }

        return $this->successResponse(new ProdutoResource($produto));
    }

    public function destroy(int $id): JsonResponse
    {
        $produto = $this->produtoService->encontrar($id);

        if (! $produto) {
            return $this->errorResponse('Produto não encontrado.', 404);
        }

        $produto = $this->produtoService->desativar($produto);

        return $this->successResponse(new ProdutoResource($produto), 'Produto desativado.');
    }

    public function buscarErp(ListProdutoErpRequest $request): JsonResponse
    {
        $data = $request->validated();

        $produtos = $this->produtoImportService->buscarNoErp(
            $data['empresa'],
            $data['nome'] ?? null,
            $data['sub_grupo'] ?? null
        );

        return $this->successResponse($produtos);
    }

    public function subGruposErp(ListSubGruposErpRequest $request): JsonResponse
    {
        $subGrupos = $this->produtoImportService->buscarSubGruposNoErp($request->validated('empresa'));

        return $this->successResponse($subGrupos);
    }

    public function buscarPecaPorCodigo(BuscarPecaPorCodigoRequest $request): JsonResponse
    {
        $pecas = $this->produtoService->buscarPorCodigoBarra($request->validated('codigo'));

        return $this->successResponse(ProdutoPecaResource::collection($pecas));
    }

    public function importar(ImportarProdutoRequest $request): JsonResponse
    {
        $produto = $this->produtoImportService->importar($request->validated());

        return $this->successResponse(new ProdutoResource($produto), 'Produto importado.', 201);
    }
}
