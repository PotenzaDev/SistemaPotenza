<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsuarioSistemaService
{
    public function criar(array $data): User
    {
        $user = User::create([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'password'             => Hash::make($data['password']),
            'role'                 => $data['role'],
            'must_change_password' => true,
        ]);

        if ($data['role'] === 'funcionario') {
            $user->rotinas()->sync($data['rotina_ids'] ?? []);
        }

        return $user->load('rotinas');
    }

    public function atualizar(User $user, array $data): User
    {
        $role = $data['role'] ?? $user->role;

        $fields = array_filter([
            'name'     => $data['name'] ?? null,
            'email'    => $data['email'] ?? null,
            'password' => isset($data['password']) && $data['password']
                            ? Hash::make($data['password'])
                            : null,
            'role'     => $data['role'] ?? null,
            'ativo'    => $data['ativo'] ?? null,
        ], fn ($v) => $v !== null);

        $user->update($fields);

        if ($role === 'funcionario') {
            if (array_key_exists('rotina_ids', $data)) {
                $user->rotinas()->sync($data['rotina_ids'] ?? []);
            }
        } else {
            $user->rotinas()->sync([]);
        }

        return $user->fresh('rotinas');
    }
}
