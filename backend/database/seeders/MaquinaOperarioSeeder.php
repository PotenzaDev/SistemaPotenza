<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EtapaFluxo;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MaquinaOperarioSeeder extends Seeder
{
    public function run(): void
    {
        EtapaFluxo::all()->each(function (EtapaFluxo $etapa): void {
            $slug = Str::slug($etapa->nome);

            Maquina::firstOrCreate(
                ['codigo' => "{$slug}-01"],
                [
                    'etapa_fluxo_id' => $etapa->id,
                    'nome' => "{$etapa->nome} 01",
                    'ativa' => true,
                ]
            );

            $user = User::firstOrCreate(
                ['email' => "operario.{$slug}@potenza.com"],
                [
                    'name' => "Operário {$etapa->nome}",
                    'password' => Hash::make('password'),
                    'role' => 'operario',
                    'ativo' => true,
                ]
            );

            Operario::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'matricula' => strtoupper("{$slug}-001"),
                    'cargo' => 'Operador',
                    'etapa_fluxo_id' => $etapa->id,
                ]
            );
        });
    }
}
