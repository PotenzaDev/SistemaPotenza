<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->time('intervalo_inicio')->nullable()->after('hora_fim');
            $table->time('intervalo_fim')->nullable()->after('intervalo_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn(['intervalo_inicio', 'intervalo_fim']);
        });
    }
};
