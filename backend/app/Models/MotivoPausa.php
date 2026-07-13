<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MotivoPausa extends Model
{
    use HasFactory;

    protected $table = 'motivos_pausa';

    protected $fillable = ['nome', 'ativo', 'is_sistema'];

    protected $casts = [
        'ativo'      => 'boolean',
        'is_sistema' => 'boolean',
    ];

    public function pausas(): HasMany
    {
        return $this->hasMany(Pausa::class);
    }

    /** Motivos ativos (usados em listagens). */
    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** Motivos que o operário pode selecionar manualmente (exclui motivos de sistema). */
    public function scopeOperario(Builder $query): Builder
    {
        return $query->where('ativo', true)->where('is_sistema', false);
    }

    public function desativar(): void
    {
        $this->update(['ativo' => false]);
    }

    public function garantirEditavel(string $acao): void
    {
        if ($this->is_sistema) {
            throw new BusinessException("Motivos de sistema não podem ser {$acao}.", 403);
        }
    }
}
