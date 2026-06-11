<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const GRUPOS = [
        1  => 'Pintura',
        2  => 'Arredondadeira',
        3  => 'Usinagem',
        4  => 'Compressor',
        5  => 'Coladeira',
        6  => 'Secador de Ar',
        7  => 'Exaustor',
        8  => 'Embalagem',
        9  => 'Furadeira',
        10 => 'Geral',
        11 => 'Gabine PU',
        12 => 'Arquear',
        13 => 'Seccionadora',
        14 => 'Tupia',
    ];

    /**
     * Substitui as etapas de fluxo (Matéria Prima, Corte...) pelos grupos de
     * máquina pedidos. Atualiza por `ordem` em vez de truncar para preservar
     * os vínculos de FK existentes (maquinas, apontamentos, operarios, etc.).
     */
    public function up(): void
    {
        foreach (self::GRUPOS as $ordem => $nome) {
            $existe = DB::table('etapas_fluxo')->where('ordem', $ordem)->exists();

            if ($existe) {
                DB::table('etapas_fluxo')->where('ordem', $ordem)->update([
                    'nome'       => $nome,
                    'ativa'      => true,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('etapas_fluxo')->insert([
                    'ordem'      => $ordem,
                    'nome'       => $nome,
                    'ativa'      => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('etapas_fluxo')->where('ordem', '>', count(self::GRUPOS))->delete();
    }

    public function down(): void
    {
        // Substituição de dados não é reversível com segurança (nomes antigos
        // não têm correspondência 1:1 com os novos grupos).
    }
};
