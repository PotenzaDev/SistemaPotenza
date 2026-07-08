<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessoes_trabalho', function (Blueprint $table) {
            // Preenchido junto com turno_informado_inicio — define o fim da janela
            // útil ad hoc daquele dia/sessão/máquina para os relatórios, no lugar
            // da janela de fallback fixa 06:00-12:00.
            $table->time('turno_informado_fim')->nullable()->after('turno_informado_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('sessoes_trabalho', function (Blueprint $table) {
            $table->dropColumn('turno_informado_fim');
        });
    }
};
