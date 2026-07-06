<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas_cabecote', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_peca_id')
                ->constrained('produto_pecas')
                ->cascadeOnDelete();
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas');
            $table->foreignId('operario_id')->nullable()->constrained('operarios');
            $table->date('data')->nullable();
            $table->decimal('top_esquerdo_mm', 8, 2)->nullable();
            $table->decimal('top_direito_mm', 8, 2)->nullable();
            $table->unsignedInteger('quantidade_pecas_vez')->nullable();
            $table->decimal('velocidade_trabalho', 8, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas_cabecote');
    }
};
