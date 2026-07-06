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
        Schema::create('brocas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->decimal('espessura_mm', 6, 2);
            $table->string('rotacao', 10);
            $table->decimal('altura_mm', 6, 2);
            $table->boolean('furo_passante');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE brocas ADD CONSTRAINT brocas_rotacao_check CHECK (rotacao IN ('direita', 'esquerda'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('brocas');
    }
};
