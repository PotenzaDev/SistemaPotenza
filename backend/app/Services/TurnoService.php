<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Turno;
use Carbon\Carbon;

class TurnoService
{
    private const DIAS_SEMANA = [1, 2, 3, 4, 5, 6, 7];

    /**
     * Se a versão atual ainda não esteve em vigor em nenhum dia passado
     * (foi criada hoje, ou é o primeiro cadastro), editá-la no lugar não
     * afeta relatório nenhum. Caso contrário, preserva-se a versão antiga
     * (usada pelos relatórios de dias já ocorridos) e cria-se uma nova,
     * em vigor a partir de hoje.
     */
    public function atualizar(int $diaSemana, array $data): Turno
    {
        if (! in_array($diaSemana, self::DIAS_SEMANA, true)) {
            throw new BusinessException('Dia da semana inválido.', 422);
        }

        $data['intervalo_inicio'] ??= null;
        $data['intervalo_fim']    ??= null;
        $data['ativo']            ??= true;

        $hoje        = Carbon::today();
        $versaoAtual = Turno::versaoAtual($diaSemana);

        if ($versaoAtual && $versaoAtual->vigente_desde->greaterThanOrEqualTo($hoje)) {
            $versaoAtual->update($data);

            return $versaoAtual->fresh();
        }

        return Turno::create($data + [
            'dia_semana'    => $diaSemana,
            'vigente_desde' => $hoje->toDateString(),
        ])->fresh();
    }
}
