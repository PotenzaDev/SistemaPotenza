<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracaoCabecoteMaquina extends Model
{
    protected $table = 'configuracoes_cabecote_maquinas';

    protected $fillable = [
        'maquina_id',
        'cabecotes_inferiores',
        'cabecotes_superiores',
        'cabecotes_topo',
        'cabecotes_traseiros',
        'pinos_por_cabecote',
    ];

    protected $casts = [
        'cabecotes_inferiores' => 'integer',
        'cabecotes_superiores' => 'integer',
        'cabecotes_topo' => 'integer',
        'cabecotes_traseiros' => 'integer',
        'pinos_por_cabecote' => 'integer',
    ];

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }
}
