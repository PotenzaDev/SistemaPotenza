<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fotos_ordens_manutencao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_manutencao_id')
                ->constrained('ordens_manutencao')
                ->cascadeOnDelete();
            $table->string('path', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_ordens_manutencao');
    }
};
