<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Services\ActivityLogService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly AuthService        $authService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        $this->activityLog->record($result['user'], 'login', 'Entrou no sistema.', $request);

        return $this->successResponse(
            [
                'user'                     => new UserResource($result['user']),
                'token'                    => $result['token'],
                'requires_password_change' => $result['requires_password_change'],
            ],
            'Login realizado com sucesso.'
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->authService->changePassword(
            $request->user(),
            $data['current_password'] ?? '',
            $data['password']
        );

        $this->activityLog->record($request->user(), 'trocar_senha', 'Alterou a própria senha.', $request);

        return $this->successResponse(null, 'Senha alterada com sucesso.');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'current_password' => ['nullable', 'string'],
            'new_password'     => ['nullable', 'string', 'min:6'],
        ]);

        $this->authService->updateProfile(
            $request->user(),
            $data['name'],
            $data['current_password'] ?? null,
            $data['new_password'] ?? null,
        );

        $this->activityLog->record($request->user(), 'atualizar_perfil', 'Atualizou nome ou senha do perfil.', $request);

        return $this->successResponse(
            new UserResource($request->user()->fresh()),
            'Perfil atualizado com sucesso.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->activityLog->record($user, 'logout', 'Saiu do sistema.', $request);
        $this->authService->logout($user);

        return $this->successResponse(null, 'Logout realizado com sucesso.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()->load('operario')),
            'Dados do usuário autenticado.'
        );
    }
}
