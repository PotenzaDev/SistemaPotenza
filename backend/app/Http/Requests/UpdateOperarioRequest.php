<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('operario')?->user_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $userId],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'etapa_fluxo_id' => ['sometimes', 'integer', 'exists:etapas_fluxo,id'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
