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
        Schema::table('pausas', function (Blueprint $table) {
            $table->foreignId('sessao_trabalho_id')
                ->nullable()
                ->after('apontamento_id')
                ->constrained('sessoes_trabalho')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE pausas ALTER COLUMN apontamento_id DROP NOT NULL');
        DB::statement('ALTER TABLE pausas ALTER COLUMN fase DROP NOT NULL');

        DB::statement('ALTER TABLE pausas DROP CONSTRAINT IF EXISTS pausas_alvo_check');
        DB::statement('ALTER TABLE pausas ADD CONSTRAINT pausas_alvo_check
            CHECK (
                (apontamento_id IS NOT NULL AND sessao_trabalho_id IS NULL)
                OR (apontamento_id IS NULL AND sessao_trabalho_id IS NOT NULL)
            )');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pausas DROP CONSTRAINT IF EXISTS pausas_alvo_check');

        DB::table('pausas')->whereNull('apontamento_id')->delete();

        DB::statement('ALTER TABLE pausas ALTER COLUMN apontamento_id SET NOT NULL');
        DB::statement('ALTER TABLE pausas ALTER COLUMN fase SET NOT NULL');

        Schema::table('pausas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sessao_trabalho_id');
        });
    }
};
