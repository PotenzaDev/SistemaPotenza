<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicoOrdemManutencao extends Model
{
    protected $table = 'servicos_ordens_manutencao';

    protected $fillable = [
        'ordem_manutencao_id',
        'servico',
        'descricao',
        'valor',
        'data',
    ];

    protected $casts = [
        'valor' => 'float',
        'data'  => 'date',
    ];

    public function ordemManutencao(): BelongsTo
    {
        return $this->belongsTo(OrdemManutencao::class);
    }
}
