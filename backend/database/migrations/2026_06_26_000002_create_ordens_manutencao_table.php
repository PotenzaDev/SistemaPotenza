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
        Schema::create('ordens_manutencao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->constrained('maquinas');
            $table->string('solicitante', 150);
            $table->text('motivo');
            $table->string('prioridade', 20)->default('normal');
            $table->string('status', 30)->default('aberta');
            $table->text('observacoes')->nullable();
            $table->timestamp('solicitado_em')->useCurrent();
            $table->timestamp('atendido_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE ordens_manutencao ADD CONSTRAINT ordens_manutencao_prioridade_check CHECK (prioridade IN ('baixa', 'normal', 'alta', 'critica'))");
        DB::statement("ALTER TABLE ordens_manutencao ADD CONSTRAINT ordens_manutencao_status_check CHECK (status IN ('aberta', 'em_atendimento', 'concluida', 'cancelada'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ordens_manutencao DROP CONSTRAINT IF EXISTS ordens_manutencao_status_check');
        DB::statement('ALTER TABLE ordens_manutencao DROP CONSTRAINT IF EXISTS ordens_manutencao_prioridade_check');

        Schema::dropIfExists('ordens_manutencao');
    }
};
