<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRelatorioMaquinaRequest;
use App\Http\Requests\ListTimelineMaquinaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Services\RelatorioProducaoService;
use App\Services\TimelineMaquinaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

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
    public function index(ListRelatorioMaquinaRequest $request): JsonResponse
    {
        $filtros = $request->validated();

        $dataInicio = isset($filtros['data_inicio']) ? Carbon::parse($filtros['data_inicio']) : Carbon::today();
        $dataFim = isset($filtros['data_fim']) ? Carbon::parse($filtros['data_fim']) : $dataInicio->copy();

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
    public function timeline(ListTimelineMaquinaRequest $request): JsonResponse
    {
        $filtros = $request->validated();

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
