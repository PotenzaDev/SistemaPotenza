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
        Schema::table('turnos', function (Blueprint $table) {
            $table->date('vigente_desde')->nullable()->after('dia_semana');
        });

        // Linhas existentes passam a valer "desde sempre", para não alterar
        // retroativamente nenhum relatório histórico já calculado com elas.
        DB::table('turnos')->update(['vigente_desde' => '2000-01-01']);

        Schema::table('turnos', function (Blueprint $table) {
            $table->date('vigente_desde')->nullable(false)->change();
            $table->unique(['dia_semana', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropUnique(['dia_semana', 'vigente_desde']);
            $table->dropColumn('vigente_desde');
        });
    }
};
