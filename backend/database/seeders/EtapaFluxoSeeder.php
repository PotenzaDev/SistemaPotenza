<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EtapaFluxo;
use Illuminate\Database\Seeder;

class EtapaFluxoSeeder extends Seeder
{
    public function run(): void
    {
        $etapas = [
            ['nome' => 'Pintura',         'ordem' => 1],
            ['nome' => 'Arredondadeira',  'ordem' => 2],
            ['nome' => 'Usinagem',        'ordem' => 3],
            ['nome' => 'Compressor',      'ordem' => 4],
            ['nome' => 'Coladeira',       'ordem' => 5],
            ['nome' => 'Secador de Ar',   'ordem' => 6],
            ['nome' => 'Exaustor',        'ordem' => 7],
            ['nome' => 'Embalagem',       'ordem' => 8],
            ['nome' => 'Furadeira',       'ordem' => 9],
            ['nome' => 'Geral',           'ordem' => 10],
            ['nome' => 'Gabine PU',       'ordem' => 11],
            ['nome' => 'Arquear',         'ordem' => 12],
            ['nome' => 'Seccionadora',    'ordem' => 13],
            ['nome' => 'Tupia',           'ordem' => 14],
        ];

        foreach ($etapas as $etapa) {
            EtapaFluxo::firstOrCreate(
                ['ordem' => $etapa['ordem']],
                ['nome' => $etapa['nome'], 'ativa' => true]
            );
        }
    }
}
