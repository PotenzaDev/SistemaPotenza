<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    use ApiResponseTrait;

    private const DIAS_SEMANA = [1, 2, 3, 4, 5, 6, 7];

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
    public function update(Request $request, int $diaSemana): JsonResponse
    {
        if (! in_array($diaSemana, self::DIAS_SEMANA, true)) {
            return $this->errorResponse('Dia da semana inválido.', 422);
        }

        $data = $request->validate([
            'hora_inicio'                    => ['required', 'date_format:H:i'],
            'hora_fim'                       => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_inicio'                => ['nullable', 'date_format:H:i', 'required_with:intervalo_fim', 'after:hora_inicio', 'before:hora_fim'],
            'intervalo_fim'                   => ['nullable', 'date_format:H:i', 'required_with:intervalo_inicio', 'after:intervalo_inicio', 'before:hora_fim'],
            'tolerancia_finalizacao_minutos' => ['required', 'integer', 'min:0', 'max:120'],
            'ativo'                          => ['boolean'],
        ]);

        $campos = [
            'hora_inicio'                     => $data['hora_inicio'],
            'hora_fim'                        => $data['hora_fim'],
            'intervalo_inicio'                => $data['intervalo_inicio'] ?? null,
            'intervalo_fim'                   => $data['intervalo_fim'] ?? null,
            'tolerancia_finalizacao_minutos'  => $data['tolerancia_finalizacao_minutos'],
            'ativo'                           => $data['ativo'] ?? true,
        ];

        $hoje         = Carbon::today();
        $versaoAtual  = Turno::versaoAtual($diaSemana);

        // Se a versão atual ainda não esteve em vigor em nenhum dia passado
        // (foi criada hoje, ou é o primeiro cadastro), editá-la no lugar não
        // afeta relatório nenhum. Caso contrário, preserva-se a versão antiga
        // (usada pelos relatórios de dias já ocorridos) e cria-se uma nova,
        // em vigor a partir de hoje.
        if ($versaoAtual && $versaoAtual->vigente_desde->greaterThanOrEqualTo($hoje)) {
            $versaoAtual->update($campos);
            $turno = $versaoAtual;
        } else {
            $turno = Turno::create($campos + [
                'dia_semana'    => $diaSemana,
                'vigente_desde' => $hoje->toDateString(),
            ]);
        }

        return $this->successResponse($turno->fresh(), 'Turno atualizado.');
    }
}
