<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FichaApontamento extends Model
{
    protected $table = 'fichas_apontamento';

    protected $fillable = [
        'apontamento_id',
        'cod_peca',
        'cod_produto',
        'cor_codigo',
        'pilha',
        'qtd_peca',
        'qtd_produzida',
        'bipada_at',
        'fim_producao',
        'duracao_segundos',
    ];

    protected $casts = [
        'bipada_at'        => 'datetime',
        'fim_producao'     => 'datetime',
        'qtd_peca'         => 'integer',
        'pilha'            => 'integer',
        'qtd_produzida'    => 'integer',
        'duracao_segundos' => 'integer',
    ];

    public function apontamento(): BelongsTo
    {
        return $this->belongsTo(Apontamento::class);
    }

    /** Calcula total de pilhas do lote: ceil(qtde_total / ftec_peca_pilha da ficha técnica) */
    public function getTotalPilhasAttribute(): int
    {
        $qtdeTotal     = $this->apontamento?->qtde_total;
        $ftecPecaPilha = $this->apontamento?->ftec_peca_pilha;

        if (! $qtdeTotal || ! $ftecPecaPilha) {
            return 0;
        }

        return (int) ceil($qtdeTotal / $ftecPecaPilha);
    }
}
