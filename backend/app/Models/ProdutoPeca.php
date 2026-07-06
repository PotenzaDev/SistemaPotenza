<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProdutoPeca extends Model
{
    use HasFactory;

    protected $fillable = [
        'produto_id',
        'numero',
        'nome',
        'sub_grupo',
        'dimensao',
        'material',
        'ordem',
    ];

    protected $casts = [
        'numero' => 'integer',
        'ordem' => 'integer',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function fichasCabecote(): HasMany
    {
        return $this->hasMany(FichaCabecote::class)->orderByDesc('data');
    }

    public function ultimaFichaCabecote(): HasOne
    {
        return $this->hasOne(FichaCabecote::class)->latestOfMany('data');
    }
}
