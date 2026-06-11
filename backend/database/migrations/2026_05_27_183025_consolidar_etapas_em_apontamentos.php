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
        // 1. Adiciona as colunas de tempo direto no apontamento
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->timestamp('setup_inicio')->nullable()->after('status');
            $table->timestamp('setup_fim')->nullable()->after('setup_inicio');
            $table->unsignedInteger('setup_duracao_segundos')->nullable()->after('setup_fim');

            $table->timestamp('producao_inicio')->nullable()->after('setup_duracao_segundos');
            $table->timestamp('producao_fim')->nullable()->after('producao_inicio');
            $table->unsignedInteger('producao_duracao_segundos')->nullable()->after('producao_fim');
        });

        // 2. Migra dados existentes de etapas_producao → apontamentos
        $etapas = DB::table('etapas_producao')->get();

        foreach ($etapas as $etapa) {
            if ($etapa->tipo === 'setup') {
                DB::table('apontamentos')->where('id', $etapa->apontamento_id)->update([
                    'setup_inicio'           => $etapa->inicio,
                    'setup_fim'              => $etapa->fim,
                    'setup_duracao_segundos' => $etapa->duracao_segundos,
                ]);
            } elseif ($etapa->tipo === 'producao') {
                DB::table('apontamentos')->where('id', $etapa->apontamento_id)->update([
                    'producao_inicio'           => $etapa->inicio,
                    'producao_fim'              => $etapa->fim,
                    'producao_duracao_segundos' => $etapa->duracao_segundos,
                ]);
            }
        }

        // 3. Remove a tabela agora desnecessária
        Schema::dropIfExists('etapas_producao');
    }

    public function down(): void
    {
        // Recria a tabela etapas_producao
        Schema::create('etapas_producao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apontamento_id')->constrained('apontamentos')->cascadeOnDelete();
            $table->enum('tipo', ['setup', 'producao']);
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fim')->nullable();
            $table->unsignedInteger('duracao_segundos')->nullable();
            $table->timestamps();
        });

        // Restaura dados a partir das colunas do apontamento
        $apontamentos = DB::table('apontamentos')
            ->whereNotNull('setup_inicio')
            ->orWhereNotNull('producao_inicio')
            ->get();

        foreach ($apontamentos as $ap) {
            if ($ap->setup_inicio) {
                DB::table('etapas_producao')->insert([
                    'apontamento_id'   => $ap->id,
                    'tipo'             => 'setup',
                    'inicio'           => $ap->setup_inicio,
                    'fim'              => $ap->setup_fim,
                    'duracao_segundos' => $ap->setup_duracao_segundos,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
            if ($ap->producao_inicio) {
                DB::table('etapas_producao')->insert([
                    'apontamento_id'   => $ap->id,
                    'tipo'             => 'producao',
                    'inicio'           => $ap->producao_inicio,
                    'fim'              => $ap->producao_fim,
                    'duracao_segundos' => $ap->producao_duracao_segundos,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        // Remove as colunas adicionadas no up()
        Schema::table('apontamentos', function (Blueprint $table) {
            $table->dropColumn([
                'setup_inicio', 'setup_fim', 'setup_duracao_segundos',
                'producao_inicio', 'producao_fim', 'producao_duracao_segundos',
            ]);
        });
    }
};
