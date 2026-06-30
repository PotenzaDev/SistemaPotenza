<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Busca o nome real da constraint em (apontamento_id, pilha) sem cod_peca,
        // independentemente do nome com que foi criada no banco.
        $existing = DB::selectOne("
            SELECT con.conname
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_attribute a1 ON a1.attrelid = rel.oid
                AND a1.attnum = ANY(con.conkey) AND a1.attname = 'apontamento_id'
            JOIN pg_attribute a2 ON a2.attrelid = rel.oid
                AND a2.attnum = ANY(con.conkey) AND a2.attname = 'pilha'
            LEFT JOIN pg_attribute a3 ON a3.attrelid = rel.oid
                AND a3.attnum = ANY(con.conkey) AND a3.attname = 'cod_peca'
            WHERE rel.relname = 'fichas_apontamento'
              AND con.contype = 'u'
              AND a3.attname IS NULL
        ");

        if ($existing) {
            DB::statement("ALTER TABLE fichas_apontamento DROP CONSTRAINT \"{$existing->conname}\"");
        }

        Schema::table('fichas_apontamento', function (Blueprint $table) {
            // Permite bipar a mesma pilha de variantes do mesmo produto (mesmo prefixo, cod_peca diferente)
            $table->unique(['apontamento_id', 'cod_peca', 'pilha'], 'unique_pilha_cod_peca_por_apontamento');
        });
    }

    public function down(): void
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

        Schema::table('fichas_apontamento', function (Blueprint $table) {
            $table->unique(['apontamento_id', 'pilha'], 'unique_pilha_por_apontamento');
        });
    }
};
