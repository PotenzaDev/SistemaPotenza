<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FotoOrdemManutencao extends Model
{
    protected $table = 'fotos_ordens_manutencao';

    protected $fillable = [
        'ordem_manutencao_id',
        'path',
    ];

    public function ordemManutencao(): BelongsTo
    {
        return $this->belongsTo(OrdemManutencao::class);
    }
}
