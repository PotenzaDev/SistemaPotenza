<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'email'                => $this->email,
            'role'                 => $this->role,
            'must_change_password' => (bool) $this->must_change_password,
            'operario'             => $this->when(
                $this->relationLoaded('operario') && $this->operario,
                fn () => [
                    'id'        => $this->operario->id,
                    'matricula' => $this->operario->matricula,
                    'cargo'     => $this->operario->cargo,
                ]
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
