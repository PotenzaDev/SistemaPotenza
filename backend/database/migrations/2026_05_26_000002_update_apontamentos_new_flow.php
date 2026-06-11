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
        // 1. Remove unique constraint de pilha (migra para fichas_apontamento)
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->dropUnique('unique_pilha_por_etapa');
        });

        // 2. Torna pilha/qtd_peca/qtd_produzida nullable (dados antigos preservados)
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->unsignedInteger('qtd_peca')->nullable()->change();
            $table->unsignedSmallInteger('pilha')->nullable()->change();
            $table->unsignedInteger('qtd_produzida')->nullable()->change();
        });

        // 3. Adiciona status aguardando_producao
        // PostgreSQL não suporta ->change() em enum, alteramos via constraint CHECK
        DB::statement("ALTER TABLE apontamentos DROP CONSTRAINT IF EXISTS apontamentos_status_check");
        DB::statement("ALTER TABLE apontamentos ADD CONSTRAINT apontamentos_status_check
            CHECK (status IN ('em_setup','aguardando_producao','em_producao','finalizado'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE apontamentos DROP CONSTRAINT IF EXISTS apontamentos_status_check");
        DB::statement("ALTER TABLE apontamentos ADD CONSTRAINT apontamentos_status_check
            CHECK (status IN ('em_setup','em_producao','finalizado'))");

        Schema::table('apontamentos', function (Blueprint $table) {
            $table->unique(['etapa_fluxo_id', 'cod_peca', 'ordem_lote', 'pilha'], 'unique_pilha_por_etapa');
        });
    }
};
