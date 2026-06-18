<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mustChange = $this->user()?->must_change_password ?? false;

        return [
            'current_password' => $mustChange ? [] : ['required', 'string'],
            'password'         => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'A senha atual é obrigatória.',
            'password.required'         => 'A nova senha é obrigatória.',
            'password.min'              => 'A nova senha deve ter pelo menos 6 caracteres.',
            'password.confirmed'        => 'A confirmação da nova senha não confere.',
        ];
    }
}
