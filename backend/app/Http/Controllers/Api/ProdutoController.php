<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Produto;
use App\Models\ProdutoPeca;
use App\Services\Produto\ProdutoImportServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdutoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ProdutoImportServiceInterface $produtoImportService
    ) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(Produto::withCount('pecas')->orderBy('nome')->get());
    }

    public function show(int $id): JsonResponse
    {
        $produto = Produto::with(['pecas' => function ($query) {
            $query->withCount('fichasCabecote')->with('ultimaFichaCabecote');
        }])->find($id);

        if (! $produto) {
            return $this->errorResponse('Produto não encontrado.', 404);
        }

        return $this->successResponse($produto);
    }

    public function destroy(int $id): JsonResponse
    {
        $produto = Produto::find($id);

        if (! $produto) {
            return $this->errorResponse('Produto não encontrado.', 404);
        }

        $produto->update(['ativo' => false]);

        return $this->successResponse($produto, 'Produto desativado.');
    }

    public function buscarErp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa' => ['required', 'string', 'in:FBM,FBP'],
            'nome' => ['nullable', 'string', 'max:255'],
            'sub_grupo' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['nome']) && empty($data['sub_grupo'])) {
            return $this->errorResponse('Informe o nome ou o sub-grupo do produto para buscar.', 422);
        }

        $produtos = $this->produtoImportService->buscarNoErp(
            $data['empresa'],
            $data['nome'] ?? null,
            $data['sub_grupo'] ?? null
        );

        return $this->successResponse($produtos);
    }

    public function subGruposErp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa' => ['required', 'string', 'in:FBM,FBP'],
        ]);

        $subGrupos = $this->produtoImportService->buscarSubGruposNoErp($data['empresa']);

        return $this->successResponse($subGrupos);
    }

    public function buscarPecaPorCodigo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string'],
        ]);

        // Só os 5 primeiros dígitos identificam o formato/peça; os 2
        // seguintes são a cor (CodiSemiAcabado do ERP, 7 chars: 5+2 — ver
        // frontend/src/lib/barcode.ts). Ignoramos a cor para achar
        // qualquer variante do mesmo formato, mesmo em outro produto.
        $digitos = substr((string) preg_replace('/\D/', '', $data['codigo']), 0, 7);

        if (strlen($digitos) < 5) {
            return $this->errorResponse('Informe ao menos 5 dígitos do código.', 422);
        }

        $prefixo = intdiv((int) $digitos, 100);

        $pecas = ProdutoPeca::with(['produto', 'ultimaFichaCabecote'])
            ->whereBetween('numero', [$prefixo * 100, $prefixo * 100 + 99])
            ->orderBy('ordem')
            ->get();

        return $this->successResponse($pecas);
    }

    public function importar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cod_produto' => ['required', 'string', 'max:255'],
            'nome' => ['required', 'string', 'max:255'],
            'grupo' => ['nullable', 'string', 'max:255'],
            'sub_grupo' => ['nullable', 'string', 'max:255'],
            'empresa' => ['required', 'string', 'in:FBM,FBP'],
        ]);

        $produto = $this->produtoImportService->importar($data);

        return $this->successResponse($produto, 'Produto importado.', 201);
    }
}
