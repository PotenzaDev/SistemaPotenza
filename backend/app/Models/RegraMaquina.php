<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegraMaquina extends Model
{
    protected $table = 'regras_maquinas';

    protected $fillable = [
        'maquina_id',
        'possui_setup',
        'possui_producao',
        'permite_multiplas_passagens',
        'limite_passagens',
        'permite_finalizacao_parcial',
        'permite_pecas_diferentes_lote',
    ];

    protected $casts = [
        'possui_setup' => 'boolean',
        'possui_producao' => 'boolean',
        'permite_multiplas_passagens' => 'boolean',
        'limite_passagens' => 'integer',
        'permite_finalizacao_parcial' => 'boolean',
        'permite_pecas_diferentes_lote' => 'boolean',
    ];

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }
}
