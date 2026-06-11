<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->unsignedInteger('ftec_peca_pilha')->nullable()->after('qtde_total');
        });
    }

    public function down(): void
    {
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->dropColumn('ftec_peca_pilha');
        });
    }
};
