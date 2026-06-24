<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $table = 'turnos';

    protected $fillable = [
        'dia_semana',
        'vigente_desde',
        'hora_inicio',
        'hora_fim',
        'intervalo_inicio',
        'intervalo_fim',
        'tolerancia_finalizacao_minutos',
        'ativo',
    ];

    protected $casts = [
        'dia_semana'                      => 'integer',
        'vigente_desde'                   => 'date',
        'tolerancia_finalizacao_minutos'  => 'integer',
        'ativo'                           => 'boolean',
    ];

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Versão do turno em vigor para o dia da semana em uma data de referência
     * (hoje, por padrão). Cada edição de turno cria uma nova versão a partir
     * da data em que foi salva, então esta consulta sempre retorna a versão
     * que estava realmente ativa naquela data — não a versão atual.
     *
     * @param int $diaSemanaIso 1 (segunda) a 7 (domingo), conforme Carbon::dayOfWeekIso
     */
    public static function doDia(int $diaSemanaIso, ?Carbon $referencia = null): ?self
    {
        return static::ativo()
            ->where('dia_semana', $diaSemanaIso)
            ->where('vigente_desde', '<=', ($referencia ?? Carbon::now())->toDateString())
            ->orderByDesc('vigente_desde')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Última versão cadastrada para o dia da semana, ativa ou não — usada
     * pela tela de administração, que precisa exibir/editar os horários de
     * um dia mesmo quando ele está marcado como inativo.
     */
    public static function versaoAtual(int $diaSemanaIso): ?self
    {
        return static::where('dia_semana', $diaSemanaIso)
            ->where('vigente_desde', '<=', Carbon::today()->toDateString())
            ->orderByDesc('vigente_desde')
            ->orderByDesc('id')
            ->first();
    }
}
