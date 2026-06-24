<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginCrachaRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        Auth::shouldUse('sanctum');
        Auth::setUser($result['user']);

        return $this->successResponse(
            [
                'user'                     => new UserResource($result['user']),
                'token'                    => $result['token'],
                'requires_password_change' => $result['requires_password_change'],
            ],
            'Login realizado com sucesso.'
        );
    }

    public function loginCracha(LoginCrachaRequest $request): JsonResponse
    {
        $result = $this->authService->loginPorMatricula($request->validated('matricula'));

        Auth::shouldUse('sanctum');
        Auth::setUser($result['user']);

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

        return $this->successResponse(
            new UserResource($request->user()->fresh()),
            'Perfil atualizado com sucesso.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Logout realizado com sucesso.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()->load(['operario', 'rotinas'])),
            'Dados do usuário autenticado.'
        );
    }
}
