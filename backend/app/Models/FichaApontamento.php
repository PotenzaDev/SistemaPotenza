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
        'total_pilhas',
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
        'total_pilhas'     => 'integer',
        'duracao_segundos' => 'integer',
    ];

    public function apontamento(): BelongsTo
    {
        return $this->belongsTo(Apontamento::class);
    }
}
