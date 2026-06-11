<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_apontamento', function (Blueprint $table) {
            $table->timestamp('fim_producao')->nullable()->after('bipada_at');
            $table->unsignedInteger('duracao_segundos')->nullable()->after('fim_producao');
        });
    }

    public function down(): void
    {
        Schema::table('fichas_apontamento', function (Blueprint $table) {
            $table->dropColumn(['fim_producao', 'duracao_segundos']);
        });
    }
};
