<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTurnoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Turno;
use App\Services\TurnoService;
use Illuminate\Http\JsonResponse;

class TurnoController extends Controller
{
    use ApiResponseTrait;

    private const DIAS_SEMANA = [1, 2, 3, 4, 5, 6, 7];

    public function __construct(private readonly TurnoService $turnoService)
    {
    }

    /** Lista o turno configurado para cada dia da semana (1=segunda...7=domingo). */
    public function index(): JsonResponse
    {
        $resultado = collect(self::DIAS_SEMANA)->map(function (int $dia) {
            $turno = Turno::versaoAtual($dia);

            return [
                'dia_semana'                      => $dia,
                'hora_inicio'                     => $turno?->hora_inicio,
                'hora_fim'                        => $turno?->hora_fim,
                'intervalo_inicio'                => $turno?->intervalo_inicio,
                'intervalo_fim'                   => $turno?->intervalo_fim,
                'tolerancia_finalizacao_minutos'  => $turno?->tolerancia_finalizacao_minutos ?? 10,
                'ativo'                           => $turno?->ativo ?? false,
            ];
        })->values();

        return $this->successResponse($resultado);
    }

    /** Cria ou atualiza o turno de um dia da semana (1=segunda...7=domingo). */
    public function update(UpdateTurnoRequest $request, int $diaSemana): JsonResponse
    {
        $turno = $this->turnoService->atualizar($diaSemana, $request->validated());

        return $this->successResponse($turno, 'Turno atualizado.');
    }
}
