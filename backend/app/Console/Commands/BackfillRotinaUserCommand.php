<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rotina;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillRotinaUserCommand extends Command
{
    protected $signature = 'rotinas:backfill-permissoes';

    protected $description = 'Migra os slugs salvos em users.modulos_permitidos para a tabela pivot rotina_user.';

    public function handle(): int
    {
        $rotinaIdsPorSlug = Rotina::pluck('id', 'slug');

        $usuarios = DB::table('users')
            ->where('role', 'funcionario')
            ->whereNotNull('modulos_permitidos')
            ->get(['id', 'modulos_permitidos']);

        $totalSincronizados = 0;
        $slugsNaoEncontrados = [];

        foreach ($usuarios as $usuario) {
            $slugs = json_decode($usuario->modulos_permitidos, true) ?? [];

            $rotinaIds = [];
            foreach ($slugs as $slug) {
                if ($rotinaIdsPorSlug->has($slug)) {
                    $rotinaIds[] = $rotinaIdsPorSlug->get($slug);
                } else {
                    $slugsNaoEncontrados[$slug] = true;
                }
            }

            if ($rotinaIds === []) {
                continue;
            }

            $linhas = array_map(fn (int $rotinaId) => [
                'user_id'    => $usuario->id,
                'rotina_id'  => $rotinaId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $rotinaIds);

            DB::table('rotina_user')->upsert($linhas, ['user_id', 'rotina_id'], ['updated_at']);

            $totalSincronizados++;
        }

        $this->info("Backfill concluído: {$totalSincronizados} usuário(s) sincronizado(s).");

        if ($slugsNaoEncontrados !== []) {
            $this->warn('Slugs não encontrados em rotinas: ' . implode(', ', array_keys($slugsNaoEncontrados)));
        }

        return self::SUCCESS;
    }
}
