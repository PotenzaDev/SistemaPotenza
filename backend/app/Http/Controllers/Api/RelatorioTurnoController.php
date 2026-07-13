<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRelatorioTurnoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\RelatorioProducaoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class RelatorioTurnoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly RelatorioProducaoService $relatorioService,
    ) {}

    /**
     * Relatório de tempo de turno (trabalhado, pausas e ocioso) por sessão,
     * para o dia informado. Sem filtro de data, retorna o relatório de hoje.
     */
    public function index(ListRelatorioTurnoRequest $request): JsonResponse
    {
        $filtros = $request->validated();

        $data = isset($filtros['data']) ? Carbon::parse($filtros['data']) : Carbon::today();

        return $this->successResponse(
            $this->relatorioService->relatorioPorDia($data, $filtros['operario_id'] ?? null, $filtros['maquina_id'] ?? null),
            'Relatório de turno.'
        );
    }
}
