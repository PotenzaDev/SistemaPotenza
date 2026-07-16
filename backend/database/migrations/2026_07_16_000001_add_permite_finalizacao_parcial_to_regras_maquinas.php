<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regras_maquinas', function (Blueprint $table) {
            $table->boolean('permite_finalizacao_parcial')->default(true)->after('limite_passagens');
        });
    }

    public function down(): void
    {
        Schema::table('regras_maquinas', function (Blueprint $table) {
            $table->dropColumn('permite_finalizacao_parcial');
        });
    }
};
