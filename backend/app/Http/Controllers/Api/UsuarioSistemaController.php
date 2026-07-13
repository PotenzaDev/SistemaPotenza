<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioSistemaRequest;
use App\Http\Requests\UpdateUsuarioSistemaRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Services\UsuarioSistemaService;
use Illuminate\Http\JsonResponse;

class UsuarioSistemaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UsuarioSistemaService $usuarioSistemaService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            UserResource::collection(
                User::whereIn('role', ['admin', 'funcionario'])->with('rotinas')->orderBy('name')->get()
            )
        );
    }

    public function store(StoreUsuarioSistemaRequest $request): JsonResponse
    {
        $user = $this->usuarioSistemaService->criar($request->validated());

        return $this->successResponse(new UserResource($user), 'Usuário cadastrado.', 201);
    }

    public function show(User $usuario): JsonResponse
    {
        return $this->successResponse(new UserResource($usuario->load('rotinas')));
    }

    public function update(UpdateUsuarioSistemaRequest $request, User $usuario): JsonResponse
    {
        $user = $this->usuarioSistemaService->atualizar($usuario, $request->validated());

        return $this->successResponse(new UserResource($user), 'Usuário atualizado.');
    }

    public function destroy(User $usuario): JsonResponse
    {
        $usuario->delete();

        return $this->successResponse(null, 'Usuário removido.');
    }
}
