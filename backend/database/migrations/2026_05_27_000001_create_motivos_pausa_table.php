<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivos_pausa', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->boolean('ativo')->default(true);
            // true = criado pelo sistema; não pode ser editado/excluído pelo admin
            $table->boolean('is_sistema')->default(false);
            $table->timestamps();
        });

        // Motivo de sistema: usado pelo auto-pause (beforeunload / beacon)
        DB::table('motivos_pausa')->insert([
            'nome'       => 'Saída sem pausa',
            'ativo'      => true,
            'is_sistema' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos_pausa');
    }
};
