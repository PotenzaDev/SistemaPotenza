<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function record(User $user, string $action, string $description, ?Request $request = null): void
    {
        ActivityLog::create([
            'user_id'     => $user->id,
            'user_name'   => $user->name,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $request?->ip(),
            'created_at'  => now(),
        ]);
    }
}
