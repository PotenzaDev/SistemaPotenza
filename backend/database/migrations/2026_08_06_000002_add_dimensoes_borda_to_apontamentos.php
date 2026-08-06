<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dimensões da ficha técnica do lote (Espess/Comp/Larg/QtdBorComp/QtdBorLarg,
 * vindas de FbmLoteFichaTecnica) capturadas uma vez ao bipar — mesmo padrão
 * de qtde_total. Usadas hoje só pelo fluxo de Coladeira para calcular metros
 * lineares de borda por ficha; ficam nullable para as demais etapas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->decimal('comprimento', 10, 4)->nullable()->after('ftec_peca_pilha');
            $table->decimal('largura', 10, 4)->nullable()->after('comprimento');
            $table->decimal('espessura', 10, 4)->nullable()->after('largura');
            $table->unsignedTinyInteger('qtd_bor_comp')->nullable()->after('espessura');
            $table->unsignedTinyInteger('qtd_bor_larg')->nullable()->after('qtd_bor_comp');
        });
    }

    public function down(): void
    {
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->dropColumn(['comprimento', 'largura', 'espessura', 'qtd_bor_comp', 'qtd_bor_larg']);
        });
    }
};
