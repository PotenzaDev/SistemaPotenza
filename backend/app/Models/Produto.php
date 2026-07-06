<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produto extends Model
{
    use HasFactory;

    protected $fillable = [
        'cod_produto',
        'nome',
        'grupo',
        'sub_grupo',
        'empresa',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function pecas(): HasMany
    {
        return $this->hasMany(ProdutoPeca::class)->orderBy('ordem');
    }
}
