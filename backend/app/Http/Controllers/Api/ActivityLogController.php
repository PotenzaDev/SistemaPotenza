<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListActivityLogsRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ActivityLogService $activityLogService)
    {
    }

    public function index(ListActivityLogsRequest $request): JsonResponse
    {
        $logs = $this->activityLogService->listar($request->validated());
        $logs->through(fn (ActivityLog $log) => new ActivityLogResource($log));

        return $this->successResponse($logs);
    }
}
