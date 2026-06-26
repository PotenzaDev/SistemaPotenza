<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PecaOrdemManutencao extends Model
{
    protected $table = 'pecas_ordens_manutencao';

    protected $fillable = [
        'ordem_manutencao_id',
        'descricao',
        'quantidade',
        'preco_unitario',
    ];

    protected $casts = [
        'quantidade'     => 'float',
        'preco_unitario' => 'float',
    ];

    public function ordemManutencao(): BelongsTo
    {
        return $this->belongsTo(OrdemManutencao::class);
    }
}
