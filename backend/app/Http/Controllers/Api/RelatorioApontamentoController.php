<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exports\RelatorioApontamentosExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRelatorioApontamentoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Services\RelatorioProducaoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RelatorioApontamentoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly RelatorioProducaoService $relatorioService,
    ) {}

    /**
     * Relatório de apontamentos por período: uma linha por segmento (setup,
     * produção ou pausa), para pré-visualização na tela. Sem filtros de
     * data, retorna o relatório de hoje.
     */
    public function index(ListRelatorioApontamentoRequest $request): JsonResponse
    {
        $linhas = $this->relatorioService->relatorioApontamentosPorPeriodo(...$this->parseFiltros($request));

        return $this->successResponse($linhas, 'Relatório de apontamentos.');
    }

    /**
     * Mesmo relatório de index(), exportado como planilha Excel (.xlsx).
     */
    public function export(ListRelatorioApontamentoRequest $request): BinaryFileResponse
    {
        [$dataInicio, $dataFim, $maquinaId, $grupoId, $operarioId] = $this->parseFiltros($request);

        $linhas = $this->relatorioService->relatorioApontamentosPorPeriodo($dataInicio, $dataFim, $maquinaId, $grupoId, $operarioId);

        $nomeArquivo = sprintf('relatorio-apontamentos-%s-a-%s.xlsx', $dataInicio->toDateString(), $dataFim->toDateString());

        return Excel::download(new RelatorioApontamentosExport($linhas), $nomeArquivo);
    }

    /**
     * Opções de filtro (grupos, máquinas e operários ativos) para o
     * relatório de apontamentos.
     */
    public function filtros(): JsonResponse
    {
        return $this->successResponse([
            'grupos' => EtapaFluxo::query()
                ->where('ativa', true)
                ->orderBy('ordem')
                ->get(['id', 'nome']),
            'maquinas' => Maquina::query()
                ->where('ativa', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'codigo', 'etapa_fluxo_id']),
            'operarios' => Operario::query()
                ->with('user')
                ->get()
                ->sortBy(fn (Operario $operario) => $operario->user?->name)
                ->values()
                ->map(fn (Operario $operario) => ['id' => $operario->id, 'nome' => $operario->user?->name])
                ->values(),
        ], 'Opções de filtro do relatório de apontamentos.');
    }

    /** @return array{0: Carbon, 1: Carbon, 2: int|null, 3: int|null, 4: int|null} */
    private function parseFiltros(ListRelatorioApontamentoRequest $request): array
    {
        $filtros = $request->validated();

        $dataInicio = isset($filtros['data_inicio']) ? Carbon::parse($filtros['data_inicio']) : Carbon::today();
        $dataFim    = isset($filtros['data_fim']) ? Carbon::parse($filtros['data_fim']) : $dataInicio->copy();

        return [
            $dataInicio,
            $dataFim,
            isset($filtros['maquina_id']) ? (int) $filtros['maquina_id'] : null,
            isset($filtros['grupo_id']) ? (int) $filtros['grupo_id'] : null,
            isset($filtros['operario_id']) ? (int) $filtros['operario_id'] : null,
        ];
    }
}
