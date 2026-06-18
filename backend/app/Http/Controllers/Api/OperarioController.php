<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateOperarioRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Operario;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OperarioController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            Operario::with(['user', 'etapaFluxo'])->orderBy('matricula')->get()
        );
    }

    public function store(CreateOperarioRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'password'             => Hash::make($data['password']),
            'role'                 => 'operario',
            'must_change_password' => true,
        ]);

        $matricula = 'OP-' . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);

        $operario = Operario::create([
            'user_id'        => $user->id,
            'matricula'      => $matricula,
            'etapa_fluxo_id' => $data['etapa_fluxo_id'],
        ]);

        $this->activityLog->record($request->user(), 'criar_operario', "Criou o operário {$data['name']} ({$data['email']}).", $request);

        return $this->successResponse(
            $operario->load(['user', 'etapaFluxo']),
            'Operário cadastrado.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $operario = Operario::with(['user', 'etapaFluxo'])->find($id);

        return $operario
            ? $this->successResponse($operario)
            : $this->errorResponse('Operário não encontrado.', 404);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $operario = Operario::with(['user', 'etapaFluxo'])->find($id);

        if (! $operario) {
            return $this->errorResponse('Operário não encontrado.', 404);
        }

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'email'          => ['sometimes', 'email', 'unique:users,email,' . $operario->user_id],
            'password'       => ['sometimes', 'nullable', 'string', 'min:6'],
            'etapa_fluxo_id' => ['sometimes', 'integer', 'exists:etapas_fluxo,id'],
            'ativo'          => ['sometimes', 'boolean'],
        ]);

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

        $this->activityLog->record($request->user(), 'editar_operario', "Editou o operário #{$id}.", $request);

        return $this->successResponse(
            $operario->fresh()->load(['user', 'etapaFluxo']),
            'Operário atualizado.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $operario = Operario::find($id);

        if (! $operario) {
            return $this->errorResponse('Operário não encontrado.', 404);
        }

        $nomeSalvo = $operario->user->name;
        $operario->user->delete();

        $this->activityLog->record($request->user(), 'remover_operario', "Removeu o operário {$nomeSalvo}.", $request);

        return $this->successResponse(null, 'Operário removido.');
    }
}
