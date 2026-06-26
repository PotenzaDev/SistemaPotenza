<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdemManutencao extends Model
{
    use HasFactory;

    protected $table = 'ordens_manutencao';

    protected $fillable = [
        'maquina_id',
        'solicitante',
        'motivo',
        'prioridade',
        'status',
        'observacoes',
        'solicitado_em',
        'atendido_em',
        'concluido_em',
    ];

    protected $casts = [
        'solicitado_em' => 'datetime',
        'atendido_em'   => 'datetime',
        'concluido_em'  => 'datetime',
    ];

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function pecas(): HasMany
    {
        return $this->hasMany(PecaOrdemManutencao::class);
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(ServicoOrdemManutencao::class);
    }
}
