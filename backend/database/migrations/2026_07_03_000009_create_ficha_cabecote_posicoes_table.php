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
        Schema::create('ficha_cabecote_posicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_cabecote_id')
                ->constrained('fichas_cabecote')
                ->cascadeOnDelete();
            $table->string('cabecote');
            $table->string('sentido');
            $table->decimal('largura_mm', 8, 2);
            $table->decimal('deslocamento_mm', 8, 2);
            $table->decimal('altura_cabecote_mm', 8, 2);
            $table->string('obs')->nullable();
            $table->integer('ordem');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE ficha_cabecote_posicoes ADD CONSTRAINT ficha_cabecote_posicoes_sentido_check CHECK (sentido IN ('inferior', 'superior', 'horizontal'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ficha_cabecote_posicoes');
    }
};
