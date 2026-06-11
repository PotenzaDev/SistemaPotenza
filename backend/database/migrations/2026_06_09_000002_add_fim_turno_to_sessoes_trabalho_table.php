<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessoes_trabalho', function (Blueprint $table) {
            $table->boolean('fim_turno')->default(false)->after('fim');
        });
    }

    public function down(): void
    {
        Schema::table('sessoes_trabalho', function (Blueprint $table) {
            $table->dropColumn('fim_turno');
        });
    }
};
