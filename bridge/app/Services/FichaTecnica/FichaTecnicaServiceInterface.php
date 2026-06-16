<?php

declare(strict_types=1);

namespace App\Services\FichaTecnica;

use App\Exceptions\RegistroNaoEncontradoException;

interface FichaTecnicaServiceInterface
{
    /**
     * Busca os dados técnicos do lote filtrando por CodiSemiAcabado + Lote.
     *
     * @param  string $ordemLote Código do lote (com ou sem zeros à esquerda).
     * @param  string $codPeca   CodiSemiAcabado vindo do código de barras.
     * @return array  lote, cod_produto, cod_peca, desc_peca, qtde_total e campos adicionais.
     *
     * @throws RegistroNaoEncontradoException Quando o lote/peça não existe.
     */
    public function buscarPorOrdemLote(string $ordemLote, string $codPeca): array;

    /**
     * Retorna a quantidade de peças por pilha (FtecpecaPilha) da ficha técnica
     * do produto. Retorna null se não encontrado.
     */
    public function buscarFtecPecaPilha(string $codPeca): ?int;
}
