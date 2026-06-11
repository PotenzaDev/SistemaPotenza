<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventoSessao;
use App\Models\SessaoTrabalho;
use Carbon\Carbon;

class SessaoCalculoService
{
    /**
     * Calcula os totais de tempo produtivo, pausa e intervalo de turno de
     * uma sessão a partir da linha do tempo de eventos_sessao.
     *
     * Cada evento define o "modo" do segmento até o próximo evento (ou até
     * o fim de referência, se for o último):
     * - inicio / retomada / inicio_turno → tempo produtivo
     * - pausa → tempo de pausa
     * - fim_turno → tempo de intervalo de turno
     *
     * @return array{produtivo_segundos: int, pausa_segundos: int, intervalo_segundos: int}
     */
    public function calcular(SessaoTrabalho $sessao): array
    {
        $eventos = $sessao->eventos;

        $produtivoSegundos = 0;
        $pausaSegundos     = 0;
        $intervaloSegundos = 0;

        $fimReferencia = $sessao->status === SessaoTrabalho::STATUS_ENCERRADA && $sessao->fim
            ? $sessao->fim
            : Carbon::now();

        foreach ($eventos as $indice => $evento) {
            $proximoEvento  = $eventos->get($indice + 1);
            $fimSegmento    = $proximoEvento?->ocorrido_em ?? $fimReferencia;
            $duracaoSegundos = max(0, $evento->ocorrido_em->diffInSeconds($fimSegmento));

            switch ($evento->tipo) {
                case EventoSessao::TIPO_INICIO:
                case EventoSessao::TIPO_RETOMADA:
                case EventoSessao::TIPO_INICIO_TURNO:
                    $produtivoSegundos += $duracaoSegundos;
                    break;

                case EventoSessao::TIPO_PAUSA:
                    $pausaSegundos += $duracaoSegundos;
                    break;

                case EventoSessao::TIPO_FIM_TURNO:
                    $intervaloSegundos += $duracaoSegundos;
                    break;
            }
        }

        return [
            'produtivo_segundos' => $produtivoSegundos,
            'pausa_segundos'     => $pausaSegundos,
            'intervalo_segundos' => $intervaloSegundos,
        ];
    }
}
