<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas_apontamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apontamento_id')
                ->constrained('apontamentos')
                ->cascadeOnDelete();
            $table->string('cod_peca', 20);
            $table->unsignedSmallInteger('pilha');
            $table->unsignedInteger('qtd_peca');
            $table->unsignedInteger('qtd_produzida')->nullable();
            $table->timestamp('bipada_at');
            $table->timestamps();

            // mesma pilha não pode ser bipada duas vezes no mesmo apontamento
            $table->unique(['apontamento_id', 'pilha'], 'unique_pilha_por_apontamento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas_apontamento');
    }
};
