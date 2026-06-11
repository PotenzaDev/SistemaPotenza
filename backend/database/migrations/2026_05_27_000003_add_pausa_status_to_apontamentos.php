<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE apontamentos DROP CONSTRAINT IF EXISTS apontamentos_status_check');
        DB::statement("ALTER TABLE apontamentos ADD CONSTRAINT apontamentos_status_check
            CHECK (status IN (
                'em_setup',
                'aguardando_producao',
                'em_producao',
                'em_pausa_setup',
                'em_pausa_producao',
                'finalizado'
            ))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE apontamentos DROP CONSTRAINT IF EXISTS apontamentos_status_check');
        DB::statement("ALTER TABLE apontamentos ADD CONSTRAINT apontamentos_status_check
            CHECK (status IN ('em_setup','aguardando_producao','em_producao','finalizado'))");
    }
};
