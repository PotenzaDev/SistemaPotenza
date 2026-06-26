<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('rotinas')->where('slug', 'manutencao')->value('id');

        if ($existing) {
            $parentId = $existing;
        } else {
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

        DB::table('rotinas')->insertOrIgnore([
            ['nome' => 'Painel', 'slug' => 'manutencao_painel', 'pagina' => '/admin/manutencao/painel', 'icone' => 'LayoutDashboard', 'parent_id' => $parentId, 'ordem' => 1, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('rotinas')->whereIn('slug', ['manutencao_painel', 'manutencao_solicitar', 'manutencao'])->delete();
    }
};
