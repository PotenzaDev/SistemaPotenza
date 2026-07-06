<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProdutoPeca;
use App\Services\Produto\ProdutoImportTransform;
use Illuminate\Console\Command;

class CorrigirDimensoesPecasCommand extends Command
{
    protected $signature = 'produtos:corrigir-dimensoes {--apply : Grava as correções no banco; sem essa opção roda em modo dry-run}';

    protected $description = 'Recalcula produto_pecas.dimensao com a conversão correta de unidades (comprimento/largura em metros -> mm; espessura já em mm)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');
        $pecas = ProdutoPeca::whereNotNull('dimensao')->get();

        $corrigidas = 0;
        $ignoradas = 0;

        foreach ($pecas as $peca) {
            $partes = $this->extrairPartes((string) $peca->dimensao);

            if ($partes === null) {
                $ignoradas++;
                $this->warn("#{$peca->id} \"{$peca->nome}\": dimensao \"{$peca->dimensao}\" fora do padrão esperado — ignorada.");

                continue;
            }

            [$comprimento, $largura, $espessura] = $partes;
            $novo = ProdutoImportTransform::dimensao($comprimento, $largura, $espessura);

            if ($novo === $peca->dimensao) {
                continue;
            }

            $corrigidas++;
            $this->line("#{$peca->id} \"{$peca->nome}\": \"{$peca->dimensao}\" -> \"{$novo}\"");

            if ($aplicar) {
                $peca->update(['dimensao' => $novo]);
            }
        }

        $this->info($aplicar
            ? "Concluído: {$corrigidas} peça(s) corrigida(s), {$ignoradas} ignorada(s)."
            : "Modo dry-run: {$corrigidas} peça(s) seriam corrigidas, {$ignoradas} ignorada(s). Rode com --apply para gravar.");

        return self::SUCCESS;
    }

    /**
     * Extrai os 3 valores numéricos brutos de uma string de dimensão no
     * formato antigo (sem conversão), ex.: ".3900x.1550x12.0000mm".
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function extrairPartes(string $dimensao): ?array
    {
        if (! preg_match('/^(-?\d*\.?\d+)x(-?\d*\.?\d+)x(-?\d*\.?\d+)mm$/i', trim($dimensao), $m)) {
            return null;
        }

        return [$m[1], $m[2], $m[3]];
    }
}
