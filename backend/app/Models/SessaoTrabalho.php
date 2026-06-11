<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SessaoTrabalho extends Model
{
    use HasFactory;

    protected $table = 'sessoes_trabalho';

    protected $fillable = [
        'operario_id',
        'maquina_id',
        'inicio',
        'fim',
        'fim_turno',
        'status',
    ];

    protected $casts = [
        'inicio'    => 'datetime',
        'fim'       => 'datetime',
        'fim_turno' => 'boolean',
    ];

    public const STATUS_ATIVA              = 'ativa';
    public const STATUS_INTERROMPIDA_TURNO = 'interrompida_turno';
    public const STATUS_ENCERRADA          = 'encerrada';

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoSessao::class)->orderBy('ocorrido_em');
    }

    public function operario(): BelongsTo
    {
        return $this->belongsTo(Operario::class);
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function apontamentos(): HasMany
    {
        return $this->hasMany(Apontamento::class);
    }

    public function apontamentoAtivo(): HasOne
    {
        return $this->hasOne(Apontamento::class)->whereIn('status', ['em_setup', 'em_producao']);
    }

    public function isAtiva(): bool
    {
        return $this->status === self::STATUS_ATIVA;
    }
}
