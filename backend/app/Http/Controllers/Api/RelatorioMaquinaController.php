<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Services\RelatorioProducaoService;
use App\Services\TimelineMaquinaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RelatorioMaquinaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly RelatorioProducaoService $relatorioService,
        private readonly TimelineMaquinaService $timelineService,
    ) {}

    /**
     * Relatório de produção por máquina (turno, setup, produção, parado e
     * peças produzidas) para o período informado. Sem filtros de data,
     * retorna o relatório de hoje. Sem limite de período.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d'],
            'maquina_id' => ['nullable', 'integer', 'exists:maquinas,id'],
            'grupo_id' => ['nullable', 'integer', 'exists:etapas_fluxo,id'],
        ]);

        $dataInicio = isset($filtros['data_inicio']) ? Carbon::parse($filtros['data_inicio']) : Carbon::today();
        $dataFim = isset($filtros['data_fim']) ? Carbon::parse($filtros['data_fim']) : $dataInicio->copy();

        if ($dataFim->lessThan($dataInicio)) {
            throw ValidationException::withMessages([
                'data_fim' => 'A data final deve ser maior ou igual à data inicial.',
            ]);
        }

        return $this->successResponse(
            $this->relatorioService->relatorioMaquinasPorPeriodo(
                $dataInicio,
                $dataFim,
                isset($filtros['maquina_id']) ? (int) $filtros['maquina_id'] : null,
                isset($filtros['grupo_id']) ? (int) $filtros['grupo_id'] : null,
            ),
            'Relatório de produção de máquinas.'
        );
    }

    /**
     * Linha do tempo de cada máquina para um dia (setup, produção, pausa e
     * parado, dentro da janela do turno). Sem filtro de data, retorna a
     * timeline de hoje.
     */
    public function timeline(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'data' => ['nullable', 'date_format:Y-m-d'],
            'maquina_id' => ['nullable', 'integer', 'exists:maquinas,id'],
            'grupo_id' => ['nullable', 'integer', 'exists:etapas_fluxo,id'],
        ]);

        $data = isset($filtros['data']) ? Carbon::parse($filtros['data']) : Carbon::today();

        return $this->successResponse(
            $this->timelineService->timelineDoDia(
                $data,
                isset($filtros['maquina_id']) ? (int) $filtros['maquina_id'] : null,
                isset($filtros['grupo_id']) ? (int) $filtros['grupo_id'] : null,
            ),
            'Linha do tempo de máquinas.'
        );
    }

    /**
     * Opções de filtro (setores/grupos e máquinas ativas) para o relatório
     * de produção de máquinas.
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
                ->get(['id', 'nome', 'etapa_fluxo_id']),
        ], 'Opções de filtro do relatório de máquinas.');
    }
}
