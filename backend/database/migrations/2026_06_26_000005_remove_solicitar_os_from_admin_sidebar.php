<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rotinas')->where('slug', 'manutencao_solicitar')->delete();
    }

    public function down(): void
    {
        $parentId = DB::table('rotinas')->where('slug', 'manutencao')->value('id');

        if ($parentId) {
            DB::table('rotinas')->insertOrIgnore([[
                'nome'       => 'Solicitar OS',
                'slug'       => 'manutencao_solicitar',
                'pagina'     => '/operario/manutencao/solicitar',
                'icone'      => 'PlusCircle',
                'parent_id'  => $parentId,
                'ordem'      => 2,
                'ativo'      => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]]);
        }
    }
};
