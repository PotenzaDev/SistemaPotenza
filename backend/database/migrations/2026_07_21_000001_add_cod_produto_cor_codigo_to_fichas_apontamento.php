<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_apontamento', function (Blueprint $table) {
            $table->string('cod_produto', 20)->nullable()->after('cod_peca');
            $table->string('cor_codigo', 10)->nullable()->after('cod_produto');
        });
    }

    public function down(): void
    {
        Schema::table('fichas_apontamento', function (Blueprint $table) {
            $table->dropColumn(['cod_produto', 'cor_codigo']);
        });
    }
};
