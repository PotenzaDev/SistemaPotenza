<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE eventos_sessao DROP CONSTRAINT IF EXISTS eventos_sessao_tipo_check');

        DB::statement("ALTER TABLE eventos_sessao ADD CONSTRAINT eventos_sessao_tipo_check
            CHECK (tipo IN ('inicio', 'retomada', 'pausa', 'inicio_turno', 'fim_turno', 'pausa_sessao', 'retomada_sessao', 'cancelamento'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE eventos_sessao DROP CONSTRAINT IF EXISTS eventos_sessao_tipo_check');

        DB::statement("ALTER TABLE eventos_sessao ADD CONSTRAINT eventos_sessao_tipo_check
            CHECK (tipo IN ('inicio', 'retomada', 'pausa', 'inicio_turno', 'fim_turno', 'pausa_sessao', 'retomada_sessao'))");
    }
};
