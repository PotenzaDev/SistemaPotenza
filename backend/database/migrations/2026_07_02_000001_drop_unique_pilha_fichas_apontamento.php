<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove a constraint UNIQUE (apontamento_id, cod_peca, pilha). A ficha
     * técnica legada (FbmLoteFichaTecnica) pode ter mais de um registro para
     * o mesmo lote/produto, representando fichas físicas duplicadas
     * legítimas — a regra de negócio (ApontamentoService::biparFicha, via
     * contarVezesPilhaBipada + contarFichasLote) já limita quantas vezes uma
     * pilha pode ser repetida, tornando esta constraint redundante e, na
     * prática, um bloqueio para o fluxo de confirmação já implementado.
     */
    public function up(): void
    {
        $existing = DB::selectOne("
            SELECT con.conname
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_attribute a1 ON a1.attrelid = rel.oid
                AND a1.attnum = ANY(con.conkey) AND a1.attname = 'apontamento_id'
            JOIN pg_attribute a2 ON a2.attrelid = rel.oid
                AND a2.attnum = ANY(con.conkey) AND a2.attname = 'cod_peca'
            JOIN pg_attribute a3 ON a3.attrelid = rel.oid
                AND a3.attnum = ANY(con.conkey) AND a3.attname = 'pilha'
            WHERE rel.relname = 'fichas_apontamento'
              AND con.contype = 'u'
        ");

        if ($existing) {
            DB::statement("ALTER TABLE fichas_apontamento DROP CONSTRAINT \"{$existing->conname}\"");
        }
    }

    public function down(): void
    {
        Schema::table('fichas_apontamento', function (Blueprint $table) {
            $table->unique(['apontamento_id', 'cod_peca', 'pilha'], 'unique_pilha_cod_peca_por_apontamento');
        });
    }
};
