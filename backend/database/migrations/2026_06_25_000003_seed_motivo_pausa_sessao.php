<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('motivos_pausa')->insert([
            'nome'       => 'Pausa de Sessão',
            'ativo'      => true,
            'is_sistema' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('motivos_pausa')
            ->where('nome', 'Pausa de Sessão')
            ->where('is_sistema', true)
            ->delete();
    }
};
