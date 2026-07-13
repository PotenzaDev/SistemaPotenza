<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFichaCabecoteRequest;
use App\Http\Requests\UpdateFichaCabecoteRequest;
use App\Http\Resources\FichaCabecoteResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\FichaCabecote;
use App\Models\ProdutoPeca;
use App\Services\FichaCabecotePdfDataBuilder;
use App\Services\FichaCabecoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FichaCabecoteController extends Controller
{
    use ApiResponseTrait;

    private const RELACOES_DETALHE = [
        'produtoPeca.produto',
        'maquina',
        'operario.user',
        'posicoesCabecote',
        'posicoesBroca.broca',
    ];

    private const CONTAGENS = ['posicoesCabecote', 'posicoesBroca'];

    public function __construct(
        private readonly FichaCabecoteService $fichaCabecoteService,
        private readonly FichaCabecotePdfDataBuilder $pdfDataBuilder,
    ) {}

    public function index(ProdutoPeca $produtoPeca): JsonResponse
    {
        $fichas = FichaCabecote::with(['maquina', 'operario.user'])
            ->withCount(self::CONTAGENS)
            ->where('produto_peca_id', $produtoPeca->id)
            ->orderByDesc('data')
            ->get();

        return $this->successResponse(FichaCabecoteResource::collection($fichas));
    }

    public function store(StoreFichaCabecoteRequest $request, ProdutoPeca $produtoPeca): JsonResponse
    {
        $ficha = $this->fichaCabecoteService->criar($produtoPeca, $request->validated());

        return $this->successResponse(
            new FichaCabecoteResource($ficha->load(self::RELACOES_DETALHE)),
            'Ficha criada.',
            201
        );
    }

    public function update(UpdateFichaCabecoteRequest $request, FichaCabecote $ficha): JsonResponse
    {
        $ficha = $this->fichaCabecoteService->atualizar($ficha, $request->validated());

        return $this->successResponse(
            new FichaCabecoteResource($ficha->load(self::RELACOES_DETALHE)),
            'Ficha atualizada.'
        );
    }

    public function show(FichaCabecote $ficha): JsonResponse
    {
        return $this->successResponse(
            new FichaCabecoteResource($ficha->load(self::RELACOES_DETALHE)->loadCount(self::CONTAGENS))
        );
    }

    public function pdf(FichaCabecote $ficha): Response
    {
        $ficha->load(self::RELACOES_DETALHE);

        $pdf = Pdf::loadView('pdf.ficha-cabecote', $this->pdfDataBuilder->montar($ficha->produtoPeca, $ficha));

        return $pdf->stream("ficha-cabecote-{$ficha->id}.pdf");
    }

    public function blankPdf(ProdutoPeca $produtoPeca): Response
    {
        $produtoPeca->load('produto');

        $pdf = Pdf::loadView('pdf.ficha-cabecote', $this->pdfDataBuilder->montar($produtoPeca, null));

        return $pdf->stream("ficha-cabecote-{$produtoPeca->id}-em-branco.pdf");
    }

    public function blankPdfLote(Request $request): Response
    {
        $ids = $this->parseIds($request);

        if ($ids === []) {
            return $this->errorResponse('Nenhuma peça selecionada.', 422);
        }

        $pecas = ProdutoPeca::with('produto')->whereIn('id', $ids)->get()->keyBy('id');
        $pecasOrdenadas = collect($ids)->map(fn ($id) => $pecas->get($id))->filter()->values();

        if ($pecasOrdenadas->isEmpty()) {
            return $this->errorResponse('Nenhum semi-acabado encontrado para os IDs informados.', 404);
        }

        $fichas = $pecasOrdenadas->map(fn (ProdutoPeca $peca) => $this->pdfDataBuilder->montar($peca, null))->all();

        $pdf = Pdf::loadView('pdf.ficha-cabecote-lote', ['fichas' => $fichas]);

        return $pdf->stream('fichas-cabecote-em-branco.pdf');
    }

    public function pdfLote(Request $request): Response
    {
        $ids = $this->parseIds($request);

        if ($ids === []) {
            return $this->errorResponse('Nenhuma ficha selecionada.', 422);
        }

        $fichasEncontradas = FichaCabecote::with(self::RELACOES_DETALHE)->whereIn('id', $ids)->get()->keyBy('id');
        $fichasOrdenadas = collect($ids)->map(fn ($id) => $fichasEncontradas->get($id))->filter()->values();

        if ($fichasOrdenadas->isEmpty()) {
            return $this->errorResponse('Nenhuma ficha encontrada para os IDs informados.', 404);
        }

        $fichas = $fichasOrdenadas
            ->map(fn (FichaCabecote $ficha) => $this->pdfDataBuilder->montar($ficha->produtoPeca, $ficha))
            ->all();

        $pdf = Pdf::loadView('pdf.ficha-cabecote-lote', ['fichas' => $fichas]);

        return $pdf->stream('fichas-cabecote.pdf');
    }

    /**
     * Lê o parâmetro de query "ids" (lista separada por vírgula, ex.:
     * "3,4,7") e retorna os inteiros válidos, na ordem informada.
     *
     * @return list<int>
     */
    private function parseIds(Request $request): array
    {
        $bruto = (string) $request->query('ids', '');

        $ids = array_filter(array_map('trim', explode(',', $bruto)), fn ($v) => $v !== '' && ctype_digit($v));

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
