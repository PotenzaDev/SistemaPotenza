<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FichaCabecoteBroca extends Model
{
    use HasFactory;

    protected $table = 'ficha_cabecote_brocas';

    protected $fillable = [
        'ficha_cabecote_id',
        'cabecote',
        'sentido',
        'posicao',
        'broca_id',
        'passante',
        'profundidade_mm',
        'agregado',
        'obs',
        'ordem',
    ];

    protected $casts = [
        'passante' => 'boolean',
        'profundidade_mm' => 'float',
        'ordem' => 'integer',
    ];

    public function fichaCabecote(): BelongsTo
    {
        return $this->belongsTo(FichaCabecote::class);
    }

    public function broca(): BelongsTo
    {
        return $this->belongsTo(Broca::class);
    }
}
