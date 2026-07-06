<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FichaCabecote extends Model
{
    use HasFactory;

    protected $table = 'fichas_cabecote';

    protected $fillable = [
        'produto_peca_id',
        'maquina_id',
        'operario_id',
        'data',
        'top_esquerdo_mm',
        'top_direito_mm',
        'quantidade_pecas_vez',
        'velocidade_trabalho',
        'observacao',
    ];

    protected $casts = [
        'data' => 'date',
        'top_esquerdo_mm' => 'float',
        'top_direito_mm' => 'float',
        'quantidade_pecas_vez' => 'integer',
        'velocidade_trabalho' => 'float',
    ];

    protected $appends = ['completa'];

    public function produtoPeca(): BelongsTo
    {
        return $this->belongsTo(ProdutoPeca::class);
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function operario(): BelongsTo
    {
        return $this->belongsTo(Operario::class);
    }

    public function posicoesCabecote(): HasMany
    {
        return $this->hasMany(FichaCabecotePosicao::class)->orderBy('ordem');
    }

    public function posicoesBroca(): HasMany
    {
        return $this->hasMany(FichaCabecoteBroca::class)->orderBy('ordem');
    }

    public function getCompletaAttribute(): bool
    {
        $temIdentificacao = $this->maquina_id !== null
            && $this->operario_id !== null
            && $this->data !== null
            && $this->top_esquerdo_mm !== null
            && $this->top_direito_mm !== null
            && $this->quantidade_pecas_vez !== null
            && $this->velocidade_trabalho !== null;

        $temPosicoes = ($this->posicoes_cabecote_count ?? $this->posicoesCabecote()->count()) > 0;
        $temBrocas = ($this->posicoes_broca_count ?? $this->posicoesBroca()->count()) > 0;

        return $temIdentificacao && $temPosicoes && $temBrocas;
    }
}
