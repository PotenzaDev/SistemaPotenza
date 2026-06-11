<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pausas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apontamento_id')
                ->constrained('apontamentos')
                ->cascadeOnDelete();
            $table->foreignId('motivo_pausa_id')
                ->constrained('motivos_pausa');
            $table->enum('fase', ['setup', 'producao']);
            $table->timestamp('inicio');
            $table->timestamp('fim')->nullable();
            $table->unsignedInteger('duracao_segundos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pausas');
    }
};
