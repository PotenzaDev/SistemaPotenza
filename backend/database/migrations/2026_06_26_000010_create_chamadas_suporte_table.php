<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamadas_suporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessao_trabalho_id')->constrained('sessoes_trabalho')->cascadeOnDelete();
            $table->foreignId('maquina_id')->constrained('maquinas')->cascadeOnDelete();
            $table->foreignId('operario_id')->constrained('operarios')->cascadeOnDelete();
            $table->timestamp('visualizado_em')->nullable();
            $table->timestamps();

            $table->index(['visualizado_em', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamadas_suporte');
    }
};
