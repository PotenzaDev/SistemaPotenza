<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_pecas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')
                ->constrained('produtos')
                ->cascadeOnDelete();
            $table->integer('numero');
            $table->string('nome');
            $table->string('sub_grupo')->nullable();
            $table->string('dimensao')->nullable();
            $table->string('material')->nullable();
            $table->integer('ordem');
            $table->timestamps();

            $table->unique(['produto_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_pecas');
    }
};
