<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotinas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('pagina');
            $table->string('icone')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('rotinas')->nullOnDelete();
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotinas');
    }
};
