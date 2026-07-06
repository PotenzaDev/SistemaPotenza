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
     * Monta a string de dimensão "comprimento x largura x espessura mm" a
     * partir dos valores disponíveis, ignorando os ausentes/vazios.
     *
     * No ERP, comprimento e largura vêm em metros e espessura já vem em
     * milímetros — por isso só os dois primeiros são multiplicados por
     * 1000. Retorna null se nenhum valor estiver disponível.
     */
    public static function dimensao(mixed $comprimento, mixed $largura, mixed $espessura): ?string
    {
        $partes = [
            [$comprimento, true],
            [$largura, true],
            [$espessura, false],
        ];

        $formatadas = [];

        foreach ($partes as [$valor, $emMetros]) {
            $valor = trim((string) ($valor ?? ''));

            if ($valor === '' || ! is_numeric($valor)) {
                continue;
            }

            $mm = (float) $valor;

            if ($emMetros) {
                $mm *= 1000;
            }

            $formatadas[] = self::formatarMm($mm);
        }

        if ($formatadas === []) {
            return null;
        }

        return implode(' x ', $formatadas).' mm';
    }

    private static function formatarMm(float $mm): string
    {
        $arredondado = round($mm, 2);

        if ((float) (int) $arredondado === $arredondado) {
            return (string) (int) $arredondado;
        }

        return rtrim(rtrim(number_format($arredondado, 2, '.', ''), '0'), '.');
    }
}
