<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->unsignedInteger('total_pausa_segundos')->nullable()->after('producao_duracao_segundos');
        });
    }

    public function down(): void
    {
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->dropColumn('total_pausa_segundos');
        });
    }
};
