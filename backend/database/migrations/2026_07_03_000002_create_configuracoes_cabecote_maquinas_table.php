<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_cabecote_maquinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')
                ->unique()
                ->constrained('maquinas')
                ->cascadeOnDelete();
            $table->unsignedInteger('cabecotes_inferiores')->default(0);
            $table->unsignedInteger('cabecotes_superiores')->default(0);
            $table->unsignedInteger('cabecotes_topo')->default(0);
            $table->unsignedInteger('cabecotes_traseiros')->default(0);
            $table->unsignedInteger('pinos_por_cabecote')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_cabecote_maquinas');
    }
};
