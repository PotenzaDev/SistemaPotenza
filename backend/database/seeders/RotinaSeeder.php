<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Rotina;
use Illuminate\Database\Seeder;

class RotinaSeeder extends Seeder
{
    public function run(): void
    {
        $rotinas = [
            ['nome' => 'Dashboard',          'slug' => 'dashboard',     'pagina' => '/admin/dashboard',     'icone' => 'LayoutDashboard'],
            ['nome' => 'Máquinas',           'slug' => 'maquinas',      'pagina' => '/admin/maquinas',      'icone' => 'Cpu'],
            ['nome' => 'Operários',          'slug' => 'operarios',     'pagina' => '/admin/operarios',     'icone' => 'Users'],
            ['nome' => 'Apontamentos',       'slug' => 'apontamentos',  'pagina' => '/admin/apontamentos',  'icone' => 'ClipboardList'],
            ['nome' => 'Mot. de Pausa',      'slug' => 'motivos_pausa', 'pagina' => '/admin/motivos-pausa', 'icone' => 'PauseCircle'],
            ['nome' => 'Turnos',             'slug' => 'turnos',        'pagina' => '/admin/turnos',        'icone' => 'Clock'],
            ['nome' => 'Relatórios',         'slug' => 'relatorios',    'pagina' => '/admin/relatorios',    'icone' => 'FileBarChart'],
            ['nome' => 'Kanban',             'slug' => 'kanban',        'pagina' => '/admin/kanban',        'icone' => 'LayoutGrid'],
            ['nome' => 'Log de Atividades',  'slug' => 'logs',          'pagina' => '/admin/logs',          'icone' => 'History'],
        ];

        foreach ($rotinas as $ordem => $rotina) {
            Rotina::firstOrCreate(
                ['slug' => $rotina['slug']],
                [
                    'nome'      => $rotina['nome'],
                    'pagina'    => $rotina['pagina'],
                    'icone'     => $rotina['icone'],
                    'parent_id' => null,
                    'ordem'     => $ordem,
                    'ativo'     => true,
                ]
            );
        }
    }
}
