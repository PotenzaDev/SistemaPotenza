<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Rotina;
use Illuminate\Database\Seeder;

class RotinaSeeder extends Seeder
{
    public function run(): void
    {
        $topLevel = [
            ['nome' => 'Dashboard',         'slug' => 'dashboard',    'pagina' => '/admin/dashboard',    'icone' => 'LayoutDashboard'],
            ['nome' => 'Cadastro',          'slug' => 'cadastro',     'pagina' => null,                   'icone' => 'Boxes'],
            ['nome' => 'Apontamentos',      'slug' => 'apontamentos', 'pagina' => '/admin/apontamentos', 'icone' => 'ClipboardList'],
            ['nome' => 'Relatórios',        'slug' => 'relatorios',   'pagina' => '/admin/relatorios',   'icone' => 'FileBarChart'],
            ['nome' => 'Log de Atividades', 'slug' => 'logs',         'pagina' => '/admin/logs',         'icone' => 'History'],
        ];

        $filhosPorPai = [
            'cadastro' => [
                ['nome' => 'Máquinas',            'slug' => 'maquinas',         'pagina' => '/admin/maquinas',      'icone' => 'Cpu'],
                ['nome' => 'Operários',           'slug' => 'operarios',        'pagina' => '/admin/operarios',     'icone' => 'Users'],
                ['nome' => 'Usuários do Sistema', 'slug' => 'usuarios_sistema', 'pagina' => '/admin/usuarios',      'icone' => 'ShieldCheck'],
                ['nome' => 'Mot. de Pausa',       'slug' => 'motivos_pausa',    'pagina' => '/admin/motivos-pausa', 'icone' => 'PauseCircle'],
                ['nome' => 'Turnos',              'slug' => 'turnos',           'pagina' => '/admin/turnos',        'icone' => 'Clock'],
                ['nome' => 'Rotinas',             'slug' => 'rotinas',          'pagina' => '/admin/rotinas',       'icone' => 'LayoutGrid'],
            ],
        ];

        foreach ($topLevel as $ordem => $rotina) {
            $pai = Rotina::updateOrCreate(
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

            foreach ($filhosPorPai[$rotina['slug']] ?? [] as $ordemFilho => $filho) {
                Rotina::updateOrCreate(
                    ['slug' => $filho['slug']],
                    [
                        'nome'      => $filho['nome'],
                        'pagina'    => $filho['pagina'],
                        'icone'     => $filho['icone'],
                        'parent_id' => $pai->id,
                        'ordem'     => $ordemFilho,
                        'ativo'     => true,
                    ]
                );
            }
        }
    }
}
