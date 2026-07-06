<?php

declare(strict_types=1);

namespace App\Services\Produto;

/**
 * Funções puras de transformação usadas na importação de produtos/peças
 * vindos do ERP legado (via API Bridge). Sem dependências externas —
 * testável diretamente com arrays/strings PHP.
 */
class ProdutoImportTransform
{
    /**
     * Deriva o número sequencial da peça (semi-acabado) a partir do
     * cod_peca vindo do ERP. Se o código não for numérico, usa o
     * fallback (posição da peça na lista, 1-based).
     */
    public static function numeroSemi(mixed $codPeca, int $fallback): int
    {
        $codPeca = trim((string) $codPeca);

        if (! is_numeric($codPeca)) {
            return $fallback;
        }

        return (int) (float) $codPeca;
    }

    /**
     * Monta a string de dimensão "comprimentoxlarguraxespessuramm" a
     * partir dos valores disponíveis, ignorando os ausentes/vazios.
     * Retorna null se nenhum valor estiver disponível.
     */
    public static function dimensao(mixed $comprimento, mixed $largura, mixed $espessura): ?string
    {
        $partes = [];

        foreach ([$comprimento, $largura, $espessura] as $valor) {
            $valor = trim((string) ($valor ?? ''));

            if ($valor !== '') {
                $partes[] = $valor;
            }
        }

        if ($partes === []) {
            return null;
        }

        return implode('x', $partes).'mm';
    }
}
