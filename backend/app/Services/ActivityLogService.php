<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ActivityLogService
{
    /** Lista logs de atividade paginados, com filtros opcionais de período, usuário e ação. */
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = ActivityLog::orderByDesc('created_at');

        if (! empty($filtros['from'])) {
            $query->whereDate('created_at', '>=', $filtros['from']);
        }
        if (! empty($filtros['to'])) {
            $query->whereDate('created_at', '<=', $filtros['to']);
        }
        if (! empty($filtros['user_id'])) {
            $query->where('user_id', $filtros['user_id']);
        }
        if (! empty($filtros['action'])) {
            $query->where('action', $filtros['action']);
        }

        return $query->paginate($filtros['per_page'] ?? 50);
    }

    public function record(
        ?User $user,
        string $action,
        string $description,
        ?Request $request = null,
        ?string $method = null,
        ?string $route = null,
        ?array $payload = null,
        ?int $statusCode = null,
    ): void {
        ActivityLog::create([
            'user_id'     => $user?->id,
            'user_name'   => $user?->name ?? 'Anônimo',
            'action'      => $action,
            'method'      => $method,
            'route'       => $route,
            'description' => $description,
            'payload'     => $payload,
            'status_code' => $statusCode,
            'ip_address'  => $request?->ip(),
            'created_at'  => now(),
        ]);
    }
}
