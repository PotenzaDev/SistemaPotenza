<?php

declare(strict_types=1);

namespace App\Enums;

enum ModuloSistema: string
{
    case Dashboard     = 'dashboard';
    case Maquinas      = 'maquinas';
    case Operarios     = 'operarios';
    case Apontamentos  = 'apontamentos';
    case MotivosPausa  = 'motivos_pausa';
    case Turnos        = 'turnos';
    case Relatorios    = 'relatorios';
    case Kanban        = 'kanban';
    case Logs          = 'logs';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $modulo) => $modulo->value, self::cases());
    }
}
