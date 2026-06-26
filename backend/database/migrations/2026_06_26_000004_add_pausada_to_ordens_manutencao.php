<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ordens_manutencao DROP CONSTRAINT IF EXISTS ordens_manutencao_status_check');
        DB::statement("ALTER TABLE ordens_manutencao ADD CONSTRAINT ordens_manutencao_status_check CHECK (status IN ('aberta', 'em_atendimento', 'pausada', 'concluida', 'cancelada'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ordens_manutencao DROP CONSTRAINT IF EXISTS ordens_manutencao_status_check');
        DB::statement("ALTER TABLE ordens_manutencao ADD CONSTRAINT ordens_manutencao_status_check CHECK (status IN ('aberta', 'em_atendimento', 'concluida', 'cancelada'))");
    }
};
