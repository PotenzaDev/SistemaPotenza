<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoSessao extends Model
{
    protected $table = 'eventos_sessao';

    protected $fillable = [
        'sessao_trabalho_id',
        'apontamento_id',
        'tipo',
        'ocorrido_em',
    ];

    protected $casts = [
        'ocorrido_em' => 'datetime',
    ];

    public const TIPO_INICIO          = 'inicio';
    public const TIPO_RETOMADA        = 'retomada';
    public const TIPO_PAUSA           = 'pausa';
    public const TIPO_INICIO_TURNO    = 'inicio_turno';
    public const TIPO_FIM_TURNO       = 'fim_turno';
    public const TIPO_PAUSA_SESSAO    = 'pausa_sessao';
    public const TIPO_RETOMADA_SESSAO = 'retomada_sessao';
    public const TIPO_CANCELAMENTO    = 'cancelamento';

    public function sessaoTrabalho(): BelongsTo
    {
        return $this->belongsTo(SessaoTrabalho::class);
    }

    public function apontamento(): BelongsTo
    {
        return $this->belongsTo(Apontamento::class);
    }
}
