<?php

declare(strict_types=1);

namespace App\Services\Lote;

use App\Exceptions\BusinessException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class LoteService implements LoteServiceInterface
{
    public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array
    {
        // Barcode entrega '06854'; o banco armazena '6854' — remove zeros à esquerda
        $ordemLote = ltrim($ordemLote, '0') ?: '0';

        $response = $this->get('ficha-tecnica/lote', [
            'lote'     => $ordemLote,
            'cod_peca' => $codPeca,
        ]);

        if ($response->notFound()) {
            throw new BusinessException(
                "Produto '{$codPeca}' não encontrado no lote '{$ordemLote}'.",
                422
            );
        }

        if ($response->failed()) {
            throw new BusinessException('Falha ao consultar a ficha técnica do lote.', 503);
        }

        return $response->json();
    }

    public function buscarFtecPecaPilha(string $codPeca): ?int
    {
        $response = $this->get('ficha-tecnica/pilha', [
            'cod_peca' => $codPeca,
        ]);

        if ($response->failed()) {
            throw new BusinessException('Falha ao consultar a ficha técnica da peça.', 503);
        }

        return $response->json('ftec_peca_pilha');
    }

    private function get(string $uri, array $query): Response
    {
        try {
            return Http::baseUrl((string) config('services.bridge.url'))
                ->withHeader('X-Bridge-Token', (string) config('services.bridge.token'))
                ->acceptJson()
                ->timeout(5)
                ->get($uri, $query);
        } catch (ConnectionException) {
            throw new BusinessException('Serviço de ficha técnica indisponível.', 503);
        }
    }
}
