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
        Schema::create('eventos_sessao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessao_trabalho_id')->constrained('sessoes_trabalho')->cascadeOnDelete();
            $table->foreignId('apontamento_id')->nullable()->constrained('apontamentos')->nullOnDelete();
            $table->string('tipo');
            $table->timestamp('ocorrido_em');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE eventos_sessao ADD CONSTRAINT eventos_sessao_tipo_check
            CHECK (tipo IN ('inicio', 'retomada', 'pausa', 'inicio_turno', 'fim_turno'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_sessao');
    }
};
