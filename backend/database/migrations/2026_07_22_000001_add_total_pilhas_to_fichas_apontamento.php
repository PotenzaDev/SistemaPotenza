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
            $table->unsignedInteger('total_pilhas')->nullable()->after('cor_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('fichas_apontamento', function (Blueprint $table) {
            $table->dropColumn('total_pilhas');
        });
    }
};
