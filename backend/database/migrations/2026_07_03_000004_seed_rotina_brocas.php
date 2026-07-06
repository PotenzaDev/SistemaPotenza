<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rotinas')->insertOrIgnore([
            'nome' => 'Brocas',
            'slug' => 'brocas',
            'pagina' => '/admin/brocas',
            'icone' => 'Boxes',
            'parent_id' => null,
            'ordem' => 95,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rotinas')->where('slug', 'brocas')->delete();
    }
};
