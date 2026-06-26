<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Corrige o registro pai que foi gravado com slug truncado / pagina errada
        DB::table('rotinas')
            ->where('slug', 'manutenc')
            ->update([
                'slug'       => 'manutencao',
                'nome'       => 'Manutenção',
                'pagina'     => null,
                'icone'      => 'Wrench',
                'updated_at' => now(),
            ]);

        // Garante que o pai exista com o slug correto (caso não existia)
        $parentId = DB::table('rotinas')->where('slug', 'manutencao')->value('id');

        if (! $parentId) {
            $parentId = DB::table('rotinas')->insertGetId([
                'nome'       => 'Manutenção',
                'slug'       => 'manutencao',
                'pagina'     => null,
                'icone'      => 'Wrench',
                'parent_id'  => null,
                'ordem'      => 90,
                'ativo'      => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insere o filho se ainda não existir
        DB::table('rotinas')->insertOrIgnore([[
            'nome'       => 'Painel',
            'slug'       => 'manutencao_painel',
            'pagina'     => '/admin/manutencao/painel',
            'icone'      => 'LayoutDashboard',
            'parent_id'  => $parentId,
            'ordem'      => 1,
            'ativo'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        // Remove eventual lixo do solicitar, caso exista
        DB::table('rotinas')->where('slug', 'manutencao_solicitar')->delete();
    }

    public function down(): void
    {
        DB::table('rotinas')->whereIn('slug', ['manutencao_painel', 'manutencao'])->delete();
    }
};
