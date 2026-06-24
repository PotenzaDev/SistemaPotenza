<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUsuarioSistemaRequest;
use App\Http\Requests\Admin\UpdateUsuarioSistemaRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UsuarioSistemaController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        return $this->successResponse(
            UserResource::collection(
                User::whereIn('role', ['admin', 'funcionario'])->orderBy('name')->get()
            )
        );
    }

    public function store(CreateUsuarioSistemaRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'password'             => Hash::make($data['password']),
            'role'                 => $data['role'],
            'must_change_password' => true,
            'modulos_permitidos'   => $data['role'] === 'funcionario' ? ($data['modulos_permitidos'] ?? []) : null,
        ]);

        return $this->successResponse(new UserResource($user), 'Usuário cadastrado.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::whereIn('role', ['admin', 'funcionario'])->find($id);

        return $user
            ? $this->successResponse(new UserResource($user))
            : $this->errorResponse('Usuário não encontrado.', 404);
    }

    public function update(UpdateUsuarioSistemaRequest $request, int $id): JsonResponse
    {
        $user = User::whereIn('role', ['admin', 'funcionario'])->find($id);

        if (! $user) {
            return $this->errorResponse('Usuário não encontrado.', 404);
        }

        $data = $request->validated();
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

        if (array_key_exists('modulos_permitidos', $data) || isset($data['role'])) {
            $fields['modulos_permitidos'] = $role === 'funcionario'
                ? ($data['modulos_permitidos'] ?? $user->modulos_permitidos ?? [])
                : null;
        }

        $user->update($fields);

        return $this->successResponse(new UserResource($user->fresh()), 'Usuário atualizado.');
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::whereIn('role', ['admin', 'funcionario'])->find($id);

        if (! $user) {
            return $this->errorResponse('Usuário não encontrado.', 404);
        }

        $user->delete();

        return $this->successResponse(null, 'Usuário removido.');
    }
}
