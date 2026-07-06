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
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('cod_produto')->unique();
            $table->string('nome');
            $table->string('grupo')->nullable();
            $table->string('sub_grupo');
            $table->string('empresa');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE produtos ADD CONSTRAINT produtos_empresa_check CHECK (empresa IN ('FBM', 'FBP'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
