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
        Schema::table('sessoes_trabalho', function (Blueprint $table) {
            $table->string('status')->default('ativa')->after('fim_turno');
        });

        DB::statement("UPDATE sessoes_trabalho SET status = CASE WHEN fim IS NULL THEN 'ativa' ELSE 'encerrada' END");

        DB::statement("ALTER TABLE sessoes_trabalho ADD CONSTRAINT sessoes_trabalho_status_check
            CHECK (status IN ('ativa', 'interrompida_turno', 'encerrada'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sessoes_trabalho DROP CONSTRAINT IF EXISTS sessoes_trabalho_status_check');

        Schema::table('sessoes_trabalho', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
