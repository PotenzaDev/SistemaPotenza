<?php

declare(strict_types=1);

namespace App\Services\Produto;

use App\Exceptions\BusinessException;
use App\Models\Produto;
use App\Models\ProdutoPeca;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProdutoImportService implements ProdutoImportServiceInterface
{
    public function buscarNoErp(string $empresa, ?string $nome, ?string $subGrupo): array
    {
        $response = $this->get('produtos', [
            'empresa' => $empresa,
            'nome' => $nome,
            'sub_grupo' => $subGrupo,
            'data_corte' => $this->dataCorte(),
        ]);

        if ($response->failed()) {
            throw new BusinessException('Falha ao consultar produtos no ERP.', 503);
        }

        return $response->json();
    }

    public function buscarSubGruposNoErp(string $empresa): array
    {
        $response = $this->get('produtos/sub-grupos', [
            'empresa' => $empresa,
            'data_corte' => $this->dataCorte(),
        ]);

        if ($response->failed()) {
            throw new BusinessException('Falha ao consultar sub-grupos de produtos no ERP.', 503);
        }

        return $response->json();
    }

    public function importar(array $dadosProdutoErp): Produto
    {
        $codProduto = rawurlencode((string) $dadosProdutoErp['cod_produto']);
        $response = $this->get("produtos/{$codProduto}/pecas", []);

        if ($response->failed()) {
            throw new BusinessException('Falha ao consultar peças do produto no ERP.', 503);
        }

        $pecasErp = $response->json();

        return DB::transaction(function () use ($dadosProdutoErp, $pecasErp) {
            $produto = Produto::updateOrCreate(
                ['cod_produto' => $dadosProdutoErp['cod_produto']],
                [
                    'nome' => $dadosProdutoErp['nome'],
                    'grupo' => $dadosProdutoErp['grupo'] ?? null,
                    'sub_grupo' => $dadosProdutoErp['sub_grupo'] ?? null,
                    'empresa' => $dadosProdutoErp['empresa'],
                ]
            );

            foreach ($pecasErp as $i => $peca) {
                $numero = ProdutoImportTransform::numeroSemi($peca['cod_peca'] ?? null, $i + 1);
                $dimensao = ProdutoImportTransform::dimensao(
                    $peca['comprimento'] ?? null,
                    $peca['largura'] ?? null,
                    $peca['espessura'] ?? null
                );

                $existente = ProdutoPeca::where('produto_id', $produto->id)
                    ->where('numero', $numero)
                    ->first();

                if (! $existente) {
                    ProdutoPeca::create([
                        'produto_id' => $produto->id,
                        'numero' => $numero,
                        'nome' => $peca['nome'] ?? '',
                        'sub_grupo' => $peca['sub_grupo'] ?? null,
                        'dimensao' => $dimensao,
                        'material' => $peca['tipo_mate'] ?? null,
                        'ordem' => $numero,
                    ]);
                } else {
                    $existente->update([
                        'sub_grupo' => $peca['sub_grupo'] ?? null,
                        'dimensao' => $dimensao,
                        'material' => $peca['tipo_mate'] ?? null,
                    ]);
                }
            }

            return $produto->load('pecas');
        });
    }

    private function dataCorte(): string
    {
        return now()->subMonths(12)->toDateString();
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
