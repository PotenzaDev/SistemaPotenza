<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OperarioService
{
    /**
     * Cria um novo Operário junto com seu User correspondente.
     */
    public function criar(array $data): Operario
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name'                 => $data['name'],
                'email'                => $data['email'],
                'password'             => Hash::make($data['password']),
                'role'                 => 'operario',
                'must_change_password' => true,
            ]);

            $matricula = $this->gerarMatricula($user->id);

            return Operario::create([
                'user_id'        => $user->id,
                'matricula'      => $matricula,
                'etapa_fluxo_id' => $data['etapa_fluxo_id'],
            ]);
        });
    }

    /**
     * Atualiza um Operário existente e, se necessário, seu User relacionado.
     */
    public function atualizar(Operario $operario, array $data): Operario
    {
        return DB::transaction(function () use ($operario, $data) {
            $userFields = array_filter([
                'name'     => $data['name'] ?? null,
                'email'    => $data['email'] ?? null,
                'password' => isset($data['password']) && $data['password']
                                ? Hash::make($data['password'])
                                : null,
                'ativo'    => $data['ativo'] ?? null,
            ], fn ($v) => $v !== null);

            if (! empty($userFields)) {
                $operario->user->update($userFields);
            }

            if (isset($data['etapa_fluxo_id'])) {
                $operario->update(['etapa_fluxo_id' => $data['etapa_fluxo_id']]);
            }

            return $operario->fresh();
        });
    }

    /**
     * Gera a matrícula do operário no formato OP-0001.
     * TODO: confirmar se essa regra de geração está correta / se deveria
     * considerar reaproveitamento de IDs excluídos, ano, etc.
     */
    private function gerarMatricula(int $userId): string
    {
        return 'OP-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
    }
}
