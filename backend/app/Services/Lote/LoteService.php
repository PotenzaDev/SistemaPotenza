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

    public function contarFichasLote(string $ordemLote, string $codPeca): int
    {
        $ordemLote = ltrim($ordemLote, '0') ?: '0';

        $response = $this->get('ficha-tecnica/count-fichas', [
            'lote'     => $ordemLote,
            'cod_peca' => $codPeca,
        ]);

        if ($response->failed()) {
            // fallback seguro: assume 1 ficha (comportamento original)
            return 1;
        }

        return max(1, (int) $response->json('total'));
    }

    public function buscarTotaisPorPrefixoLote(string $ordemLote, string $prefixoCod): array
    {
        $ordemLote = ltrim($ordemLote, '0') ?: '0';

        $response = $this->get('ficha-tecnica/lote-variantes', [
            'lote'        => $ordemLote,
            'prefixo_cod' => $prefixoCod,
        ]);

        if ($response->failed()) {
            return ['qtde_total' => null, 'total_pilhas' => 0];
        }

        return [
            'qtde_total'   => $response->json('qtde_total'),
            'total_pilhas' => (int) $response->json('total_pilhas'),
        ];
    }

    private function get(string $uri, array $query): Response
    {
        $url = (string) config('services.bridge.url');

        if (empty($url)) {
            throw new BusinessException(
                'A URL da API Bridge não está configurada. Verifique a variável BRIDGE_API_URL no arquivo .env.',
                503
            );
        }

        try {
            $response = Http::baseUrl($url)
                ->withHeader('X-Bridge-Token', (string) config('services.bridge.token'))
                ->acceptJson()
                ->timeout(5)
                ->get($uri, $query);
        } catch (ConnectionException) {
            throw new BusinessException(
                "Não foi possível conectar à API Bridge ({$url}). Verifique se o serviço está ativo.",
                503
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new BusinessException(
                'Autenticação negada na API Bridge. Verifique o token de acesso (BRIDGE_API_TOKEN).',
                503
            );
        }

        return $response;
    }
}
