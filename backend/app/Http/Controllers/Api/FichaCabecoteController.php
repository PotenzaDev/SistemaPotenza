<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFichaCabecoteRequest;
use App\Http\Requests\UpdateFichaCabecoteRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\FichaCabecote;
use App\Models\ProdutoPeca;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    private const LINHAS_BRANCO_CABECOTE = 7;

    private const LINHAS_BRANCO_BROCA = 24;

    private const SENTIDO_LABELS = [
        'inferior' => 'Inferior',
        'superior' => 'Superior',
        'horizontal' => 'Horizontal',
    ];

    public function index(int $pecaId): JsonResponse
    {
        $peca = ProdutoPeca::find($pecaId);

        if (! $peca) {
            return $this->errorResponse('Semi-acabado não encontrado.', 404);
        }

        $fichas = FichaCabecote::with(['maquina', 'operario.user'])
            ->withCount(self::CONTAGENS)
            ->where('produto_peca_id', $pecaId)
            ->orderByDesc('data')
            ->get();

        return $this->successResponse($fichas);
    }

    public function store(StoreFichaCabecoteRequest $request, int $pecaId): JsonResponse
    {
        $peca = ProdutoPeca::find($pecaId);

        if (! $peca) {
            return $this->errorResponse('Semi-acabado não encontrado.', 404);
        }

        $data = $request->validated();

        $ficha = DB::transaction(function () use ($data, $pecaId) {
            $ficha = FichaCabecote::create([
                'produto_peca_id' => $pecaId,
                'maquina_id' => $data['maquina_id'] ?? null,
                'operario_id' => $data['operario_id'] ?? null,
                'data' => $data['data'] ?? null,
                'top_esquerdo_mm' => $data['top_esquerdo_mm'] ?? null,
                'top_direito_mm' => $data['top_direito_mm'] ?? null,
                'quantidade_pecas_vez' => $data['quantidade_pecas_vez'] ?? null,
                'velocidade_trabalho' => $data['velocidade_trabalho'] ?? null,
                'observacao' => $data['observacao'] ?? null,
            ]);

            $this->salvarPosicoes($ficha, $data);

            return $ficha;
        });

        return $this->successResponse(
            $ficha->load(self::RELACOES_DETALHE),
            'Ficha criada.',
            201
        );
    }

    public function update(UpdateFichaCabecoteRequest $request, int $id): JsonResponse
    {
        $ficha = FichaCabecote::find($id);

        if (! $ficha) {
            return $this->errorResponse('Ficha não encontrada.', 404);
        }

        $data = $request->validated();

        DB::transaction(function () use ($ficha, $data) {
            $ficha->update([
                'maquina_id' => $data['maquina_id'] ?? null,
                'operario_id' => $data['operario_id'] ?? null,
                'data' => $data['data'] ?? null,
                'top_esquerdo_mm' => $data['top_esquerdo_mm'] ?? null,
                'top_direito_mm' => $data['top_direito_mm'] ?? null,
                'quantidade_pecas_vez' => $data['quantidade_pecas_vez'] ?? null,
                'velocidade_trabalho' => $data['velocidade_trabalho'] ?? null,
                'observacao' => $data['observacao'] ?? null,
            ]);

            $ficha->posicoesCabecote()->delete();
            $ficha->posicoesBroca()->delete();
            $this->salvarPosicoes($ficha, $data);
        });

        return $this->successResponse(
            $ficha->fresh()->load(self::RELACOES_DETALHE),
            'Ficha atualizada.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $ficha = FichaCabecote::with(self::RELACOES_DETALHE)->withCount(self::CONTAGENS)->find($id);

        if (! $ficha) {
            return $this->errorResponse('Ficha não encontrada.', 404);
        }

        return $this->successResponse($ficha);
    }

    public function pdf(int $id): Response
    {
        $ficha = FichaCabecote::with(self::RELACOES_DETALHE)->find($id);

        if (! $ficha) {
            return $this->errorResponse('Ficha não encontrada.', 404);
        }

        $pdf = Pdf::loadView('pdf.ficha-cabecote', $this->dadosParaPdf($ficha->produtoPeca, $ficha));

        return $pdf->stream("ficha-cabecote-{$ficha->id}.pdf");
    }

    public function blankPdf(int $pecaId): Response
    {
        $peca = ProdutoPeca::with('produto')->find($pecaId);

        if (! $peca) {
            return $this->errorResponse('Semi-acabado não encontrado.', 404);
        }

        $pdf = Pdf::loadView('pdf.ficha-cabecote', $this->dadosParaPdf($peca, null));

        return $pdf->stream("ficha-cabecote-{$peca->id}-em-branco.pdf");
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

        $fichas = $pecasOrdenadas->map(fn (ProdutoPeca $peca) => $this->dadosParaPdf($peca, null))->all();

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
            ->map(fn (FichaCabecote $ficha) => $this->dadosParaPdf($ficha->produtoPeca, $ficha))
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

    private function posicaoLabel(ProdutoPeca $peca): string
    {
        $subGrupo = trim((string) ($peca->sub_grupo ?? ''));

        if ($subGrupo === '') {
            return $peca->nome;
        }

        return preg_replace('/^\d+\s+/', '', $subGrupo) ?: $peca->nome;
    }

    private function dadosParaPdf(ProdutoPeca $peca, ?FichaCabecote $ficha): array
    {
        $posicoesCabecote = $ficha
            ? $ficha->posicoesCabecote->map(fn ($linha) => [
                'cabecote' => $linha->cabecote,
                'sentido' => self::SENTIDO_LABELS[$linha->sentido] ?? $linha->sentido,
                'largura_mm' => $linha->largura_mm,
                'deslocamento_mm' => $linha->deslocamento_mm,
                'altura_cabecote_mm' => $linha->altura_cabecote_mm,
                'obs' => $linha->obs,
            ])->all()
            : [];

        $posicoesBroca = $ficha
            ? $ficha->posicoesBroca->map(fn ($linha) => [
                'cabecote' => $linha->cabecote,
                'sentido' => self::SENTIDO_LABELS[$linha->sentido] ?? $linha->sentido,
                'posicao' => $linha->posicao,
                'broca_codigo' => $linha->broca?->codigo,
                'passante_label' => $linha->passante
                    ? 'SIM'
                    : ($linha->profundidade_mm !== null ? "{$linha->profundidade_mm}mm" : null),
                'agregado' => $linha->agregado,
                'obs' => $linha->obs,
            ])->all()
            : [];

        return [
            'produtoNome' => $peca->produto?->nome,
            'pecaNumero' => $peca->numero,
            'pecaNome' => $this->posicaoLabel($peca),
            'pecaDimensao' => $peca->dimensao,
            'data' => $ficha?->data?->format('d/m/Y'),
            'maquinaNome' => $ficha?->maquina?->nome,
            'operadorNome' => $ficha?->operario?->user?->name,
            'quantidadePecasVez' => $ficha?->quantidade_pecas_vez,
            'topEsquerdoMm' => $ficha?->top_esquerdo_mm,
            'topDireitoMm' => $ficha?->top_direito_mm,
            'velocidadeTrabalho' => $ficha?->velocidade_trabalho,
            'observacao' => $ficha?->observacao,
            'posicoesCabecote' => $posicoesCabecote,
            'posicoesBroca' => $posicoesBroca,
            'linhasBrancoCabecote' => self::LINHAS_BRANCO_CABECOTE,
            'linhasBrancoBroca' => self::LINHAS_BRANCO_BROCA,
            'blank' => $ficha === null,
        ];
    }

    private function salvarPosicoes(FichaCabecote $ficha, array $data): void
    {
        foreach ($data['posicoes_cabecote'] ?? [] as $i => $linha) {
            $ficha->posicoesCabecote()->create([
                'cabecote' => $linha['cabecote'],
                'sentido' => $linha['sentido'],
                'largura_mm' => $linha['largura_mm'],
                'deslocamento_mm' => $linha['deslocamento_mm'],
                'altura_cabecote_mm' => $linha['altura_cabecote_mm'],
                'obs' => $linha['obs'] ?? null,
                'ordem' => $i + 1,
            ]);
        }

        foreach ($data['posicoes_broca'] ?? [] as $i => $linha) {
            $ficha->posicoesBroca()->create([
                'cabecote' => $linha['cabecote'],
                'sentido' => $linha['sentido'],
                'posicao' => $linha['posicao'],
                'broca_id' => $linha['broca_id'],
                'passante' => $linha['passante'],
                'profundidade_mm' => $linha['profundidade_mm'] ?? null,
                'agregado' => $linha['agregado'] ?? null,
                'obs' => $linha['obs'] ?? null,
                'ordem' => $i + 1,
            ]);
        }
    }
}
