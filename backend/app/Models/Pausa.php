<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pausa extends Model
{
    protected $table = 'pausas';

    protected $fillable = [
        'apontamento_id',
        'motivo_pausa_id',
        'fase',
        'inicio',
        'fim',
        'duracao_segundos',
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fim'    => 'datetime',
    ];

    public function apontamento(): BelongsTo
    {
        return $this->belongsTo(Apontamento::class);
    }

    public function motivoPausa(): BelongsTo
    {
        return $this->belongsTo(MotivoPausa::class);
    }
}
