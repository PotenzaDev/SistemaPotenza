<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicos_ordens_manutencao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_manutencao_id')
                ->constrained('ordens_manutencao')
                ->cascadeOnDelete();
            $table->string('servico', 200);
            $table->text('descricao')->nullable();
            $table->decimal('valor', 12, 2);
            $table->date('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicos_ordens_manutencao');
    }
};
