<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pecas_ordens_manutencao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_manutencao_id')
                ->constrained('ordens_manutencao')
                ->cascadeOnDelete();
            $table->string('descricao', 200);
            $table->decimal('quantidade', 10, 3);
            $table->decimal('preco_unitario', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pecas_ordens_manutencao');
    }
};
