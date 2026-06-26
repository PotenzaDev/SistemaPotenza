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
        Schema::table('ordens_manutencao', function (Blueprint $table) {
            $table->string('prioridade', 20)->default('normal')->after('motivo');
        });

        DB::statement("ALTER TABLE ordens_manutencao ADD CONSTRAINT ordens_manutencao_prioridade_check CHECK (prioridade IN ('baixa', 'normal', 'alta', 'critica'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ordens_manutencao DROP CONSTRAINT IF EXISTS ordens_manutencao_prioridade_check');

        Schema::table('ordens_manutencao', function (Blueprint $table) {
            $table->dropColumn('prioridade');
        });
    }
};
