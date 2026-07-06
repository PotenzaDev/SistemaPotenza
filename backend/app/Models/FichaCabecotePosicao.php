<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FichaCabecotePosicao extends Model
{
    use HasFactory;

    protected $table = 'ficha_cabecote_posicoes';

    protected $fillable = [
        'ficha_cabecote_id',
        'cabecote',
        'sentido',
        'largura_mm',
        'deslocamento_mm',
        'altura_cabecote_mm',
        'obs',
        'ordem',
    ];

    protected $casts = [
        'largura_mm' => 'float',
        'deslocamento_mm' => 'float',
        'altura_cabecote_mm' => 'float',
        'ordem' => 'integer',
    ];

    public function fichaCabecote(): BelongsTo
    {
        return $this->belongsTo(FichaCabecote::class);
    }
}
