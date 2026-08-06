<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metros lineares de fita de borda consumidos nesta ficha/pilha:
 * (apontamento.comprimento * apontamento.qtd_bor_comp
 *   + apontamento.largura * apontamento.qtd_bor_larg) * qtd_peca.
 * Calculado só no fluxo de Coladeira (ApontamentoColadeiraService::biparFicha);
 * nullable para as demais etapas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_apontamento', function (Blueprint $table) {
            $table->decimal('metros_lineares_borda', 10, 4)->nullable()->after('qtd_peca');
        });
    }

    public function down(): void
    {
        Schema::table('fichas_apontamento', function (Blueprint $table) {
            $table->dropColumn('metros_lineares_borda');
        });
    }
};
