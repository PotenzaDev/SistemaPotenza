<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'from'     => ['nullable', 'date_format:Y-m-d'],
            'to'       => ['nullable', 'date_format:Y-m-d'],
            'user_id'  => ['nullable', 'integer', 'exists:users,id'],
            'action'   => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

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

        $logs = $query->paginate($filtros['per_page'] ?? 50);

        return $this->successResponse($logs);
    }
}
