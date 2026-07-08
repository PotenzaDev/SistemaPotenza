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
            // Preenchido pelo próprio operário ao iniciar sessão em um dia sem
            // turno cadastrado (ex.: sábado/domingo avulso) — usado pelos
            // relatórios como início da janela útil daquele dia/sessão.
            $table->time('turno_informado_inicio')->nullable()->after('fim_turno');
        });
    }

    public function down(): void
    {
        Schema::table('sessoes_trabalho', function (Blueprint $table) {
            $table->dropColumn('turno_informado_inicio');
        });
    }
};
