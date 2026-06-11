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
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE turnos ADD CONSTRAINT turnos_dia_semana_check CHECK (dia_semana BETWEEN 1 AND 7)');

        $agora = now();

        // Segunda (1) a quinta (4): 08h-17h. Sexta (5): 08h-16h30.
        DB::table('turnos')->insert([
            ['dia_semana' => 1, 'hora_inicio' => '08:00:00', 'hora_fim' => '17:00:00', 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora],
            ['dia_semana' => 2, 'hora_inicio' => '08:00:00', 'hora_fim' => '17:00:00', 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora],
            ['dia_semana' => 3, 'hora_inicio' => '08:00:00', 'hora_fim' => '17:00:00', 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora],
            ['dia_semana' => 4, 'hora_inicio' => '08:00:00', 'hora_fim' => '17:00:00', 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora],
            ['dia_semana' => 5, 'hora_inicio' => '08:00:00', 'hora_fim' => '16:30:00', 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
