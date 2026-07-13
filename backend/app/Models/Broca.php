<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Broca extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'espessura_mm',
        'rotacao',
        'altura_mm',
        'furo_passante',
        'ativo',
    ];

    protected $casts = [
        'espessura_mm' => 'float',
        'altura_mm' => 'float',
        'furo_passante' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function desativar(): void
    {
        $this->update(['ativo' => false]);
    }
}
