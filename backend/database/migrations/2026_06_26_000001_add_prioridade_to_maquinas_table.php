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
        Schema::table('maquinas', function (Blueprint $table) {
            $table->string('prioridade', 20)->default('normal')->after('ativa');
        });

        DB::statement("ALTER TABLE maquinas ADD CONSTRAINT maquinas_prioridade_check CHECK (prioridade IN ('baixa', 'normal', 'alta', 'critica'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE maquinas DROP CONSTRAINT IF EXISTS maquinas_prioridade_check');

        Schema::table('maquinas', function (Blueprint $table) {
            $table->dropColumn('prioridade');
        });
    }
};
