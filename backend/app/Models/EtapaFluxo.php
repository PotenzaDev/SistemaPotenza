<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtapaFluxo extends Model
{
    use HasFactory;

    protected $table = 'etapas_fluxo';

    protected $fillable = [
        'nome',
        'ordem',
        'ativa',
        'requer_config_cabecote',
        'apontamento_por_lote',
    ];

    protected $casts = [
        'ativa' => 'boolean',
        'requer_config_cabecote' => 'boolean',
        'apontamento_por_lote' => 'boolean',
    ];

    public function maquinas(): HasMany
    {
        return $this->hasMany(Maquina::class);
    }

    public function historicoLotes(): HasMany
    {
        return $this->hasMany(HistoricoLote::class);
    }

    public function apontamentos(): HasMany
    {
        return $this->hasMany(Apontamento::class);
    }

    public function proxima(): ?self
    {
        return self::where('ordem', $this->ordem + 1)->where('ativa', true)->first();
    }
}
