<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChamadaSuporte extends Model
{
    protected $table = 'chamadas_suporte';

    protected $fillable = [
        'sessao_trabalho_id',
        'maquina_id',
        'operario_id',
        'visualizado_em',
    ];

    protected $casts = [
        'visualizado_em' => 'datetime',
    ];

    public function sessaoTrabalho(): BelongsTo
    {
        return $this->belongsTo(SessaoTrabalho::class);
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function operario(): BelongsTo
    {
        return $this->belongsTo(Operario::class);
    }

    public function isVisualizada(): bool
    {
        return $this->visualizado_em !== null;
    }
}
