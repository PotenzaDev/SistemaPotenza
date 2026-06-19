<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $table = 'turnos';

    protected $fillable = [
        'dia_semana',
        'hora_inicio',
        'hora_fim',
        'intervalo_inicio',
        'intervalo_fim',
        'tolerancia_finalizacao_minutos',
        'ativo',
    ];

    protected $casts = [
        'dia_semana'                      => 'integer',
        'tolerancia_finalizacao_minutos'  => 'integer',
        'ativo'                           => 'boolean',
    ];

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** @param int $diaSemanaIso 1 (segunda) a 7 (domingo), conforme Carbon::dayOfWeekIso */
    public static function doDia(int $diaSemanaIso): ?self
    {
        return static::ativo()->where('dia_semana', $diaSemanaIso)->first();
    }
}
