<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\FichaCabecote;
use App\Models\ProdutoPeca;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class FichaCabecoteService
{
    public function criar(ProdutoPeca $peca, array $dados): FichaCabecote
    {
        if (FichaCabecote::where('produto_peca_id', $peca->id)->exists()) {
            throw new BusinessException('Esta peça já possui uma ficha cadastrada.', 409);
        }

        try {
            return DB::transaction(function () use ($peca, $dados) {
                $ficha = FichaCabecote::create([
                    'produto_peca_id' => $peca->id,
                    'maquina_id' => $dados['maquina_id'] ?? null,
                    'operario_id' => $dados['operario_id'] ?? null,
                    'data' => $dados['data'] ?? null,
                    'top_esquerdo_mm' => $dados['top_esquerdo_mm'] ?? null,
                    'top_direito_mm' => $dados['top_direito_mm'] ?? null,
                    'quantidade_pecas_vez' => $dados['quantidade_pecas_vez'] ?? null,
                    'velocidade_trabalho' => $dados['velocidade_trabalho'] ?? null,
                    'observacao' => $dados['observacao'] ?? null,
                ]);

                $this->salvarPosicoes($ficha, $dados);

                return $ficha;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new BusinessException('Esta peça já possui uma ficha cadastrada.', 409);
            }

            throw $e;
        }
    }

    public function atualizar(FichaCabecote $ficha, array $dados): FichaCabecote
    {
        DB::transaction(function () use ($ficha, $dados) {
            $ficha->update([
                'maquina_id' => $dados['maquina_id'] ?? null,
                'operario_id' => $dados['operario_id'] ?? null,
                'data' => $dados['data'] ?? null,
                'top_esquerdo_mm' => $dados['top_esquerdo_mm'] ?? null,
                'top_direito_mm' => $dados['top_direito_mm'] ?? null,
                'quantidade_pecas_vez' => $dados['quantidade_pecas_vez'] ?? null,
                'velocidade_trabalho' => $dados['velocidade_trabalho'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
            ]);

            $ficha->posicoesCabecote()->delete();
            $ficha->posicoesBroca()->delete();
            $this->salvarPosicoes($ficha, $dados);
        });

        return $ficha->fresh();
    }

    private function salvarPosicoes(FichaCabecote $ficha, array $dados): void
    {
        foreach ($dados['posicoes_cabecote'] ?? [] as $i => $linha) {
            $ficha->posicoesCabecote()->create([
                'cabecote' => $linha['cabecote'],
                'sentido' => $linha['sentido'],
                'largura_mm' => $linha['largura_mm'],
                'deslocamento_mm' => $linha['deslocamento_mm'],
                'altura_cabecote_mm' => $linha['altura_cabecote_mm'],
                'obs' => $linha['obs'] ?? null,
                'ordem' => $i + 1,
            ]);
        }

        foreach ($dados['posicoes_broca'] ?? [] as $i => $linha) {
            $ficha->posicoesBroca()->create([
                'cabecote' => $linha['cabecote'],
                'sentido' => $linha['sentido'],
                'posicao' => $linha['posicao'],
                'broca_id' => $linha['broca_id'],
                'passante' => $linha['passante'],
                'profundidade_mm' => $linha['profundidade_mm'] ?? null,
                'agregado' => $linha['agregado'] ?? null,
                'obs' => $linha['obs'] ?? null,
                'ordem' => $i + 1,
            ]);
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505';
    }
}
