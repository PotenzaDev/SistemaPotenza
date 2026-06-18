<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function login(array $credentials): array
    {
        $user = $this->userRepository->findByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new BusinessException('Credenciais inválidas.', 401);
        }

        if (! $user->ativo) {
            throw new BusinessException('Conta desativada. Entre em contato com o administrador.', 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user'                     => $user,
            'token'                    => $token,
            'requires_password_change' => $user->must_change_password,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! $user->must_change_password) {
            if (! Hash::check($currentPassword, $user->password)) {
                throw new BusinessException('Senha atual incorreta.', 422);
            }
        }

        $user->update([
            'password'             => Hash::make($newPassword),
            'must_change_password' => false,
        ]);
    }
}
