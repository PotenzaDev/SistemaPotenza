<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FichaCabecote;
use App\Models\ProdutoPeca;

class FichaCabecotePdfDataBuilder
{
    private const LINHAS_BRANCO_CABECOTE = 7;

    private const LINHAS_BRANCO_BROCA = 24;

    private const SENTIDO_LABELS = [
        'inferior' => 'Inferior',
        'superior' => 'Superior',
        'horizontal' => 'Horizontal',
    ];

    public function montar(ProdutoPeca $peca, ?FichaCabecote $ficha): array
    {
        $posicoesCabecote = $ficha
            ? $ficha->posicoesCabecote->map(fn ($linha) => [
                'cabecote' => $linha->cabecote,
                'sentido' => self::SENTIDO_LABELS[$linha->sentido] ?? $linha->sentido,
                'largura_mm' => $linha->largura_mm,
                'deslocamento_mm' => $linha->deslocamento_mm,
                'altura_cabecote_mm' => $linha->altura_cabecote_mm,
                'obs' => $linha->obs,
            ])->all()
            : [];

        $posicoesBroca = $ficha
            ? $ficha->posicoesBroca->map(fn ($linha) => [
                'cabecote' => $linha->cabecote,
                'sentido' => self::SENTIDO_LABELS[$linha->sentido] ?? $linha->sentido,
                'posicao' => $linha->posicao,
                'broca_codigo' => $linha->broca?->codigo,
                'passante_label' => $linha->passante
                    ? 'SIM'
                    : ($linha->profundidade_mm !== null ? "{$linha->profundidade_mm}mm" : null),
                'agregado' => $linha->agregado,
                'obs' => $linha->obs,
            ])->all()
            : [];

        return [
            'produtoNome' => $peca->produto?->nome,
            'pecaNumero' => $peca->numero,
            'pecaNome' => $this->posicaoLabel($peca),
            'pecaDimensao' => $peca->dimensao,
            'data' => $ficha?->data?->format('d/m/Y'),
            'maquinaNome' => $ficha?->maquina?->nome,
            'operadorNome' => $ficha?->operario?->user?->name,
            'quantidadePecasVez' => $ficha?->quantidade_pecas_vez,
            'topEsquerdoMm' => $ficha?->top_esquerdo_mm,
            'topDireitoMm' => $ficha?->top_direito_mm,
            'velocidadeTrabalho' => $ficha?->velocidade_trabalho,
            'observacao' => $ficha?->observacao,
            'posicoesCabecote' => $posicoesCabecote,
            'posicoesBroca' => $posicoesBroca,
            'linhasBrancoCabecote' => self::LINHAS_BRANCO_CABECOTE,
            'linhasBrancoBroca' => self::LINHAS_BRANCO_BROCA,
            'blank' => $ficha === null,
        ];
    }

    private function posicaoLabel(ProdutoPeca $peca): string
    {
        $subGrupo = trim((string) ($peca->sub_grupo ?? ''));

        if ($subGrupo === '') {
            return $peca->nome;
        }

        return preg_replace('/^\d+\s+/', '', $subGrupo) ?: $peca->nome;
    }
}
