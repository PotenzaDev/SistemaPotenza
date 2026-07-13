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
        Schema::create('regras_maquinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')
                ->unique()
                ->constrained('maquinas')
                ->cascadeOnDelete();
            $table->boolean('possui_setup')->default(true);
            $table->boolean('possui_producao')->default(true);
            $table->boolean('permite_multiplas_passagens')->default(true);
            $table->unsignedInteger('limite_passagens')->nullable();
            $table->timestamps();
        });

        $agora = now();

        $regrasPadrao = DB::table('maquinas')->pluck('id')->map(fn (int $maquinaId) => [
            'maquina_id' => $maquinaId,
            'possui_setup' => true,
            'possui_producao' => true,
            'permite_multiplas_passagens' => true,
            'limite_passagens' => null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        if ($regrasPadrao->isNotEmpty()) {
            DB::table('regras_maquinas')->insert($regrasPadrao->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('regras_maquinas');
    }
};
