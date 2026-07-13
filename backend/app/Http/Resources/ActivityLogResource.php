<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'action' => $this->action,
            'method' => $this->method,
            'route' => $this->route,
            'description' => $this->description,
            'payload' => $this->payload,
            'status_code' => $this->status_code,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
