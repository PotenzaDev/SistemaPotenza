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
        Schema::table('etapas_fluxo', function (Blueprint $table) {
            $table->boolean('requer_config_cabecote')->default(false);
        });

        DB::table('etapas_fluxo')->where('nome', 'Furadeira')->update([
            'requer_config_cabecote' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('etapas_fluxo', function (Blueprint $table) {
            $table->dropColumn('requer_config_cabecote');
        });
    }
};
