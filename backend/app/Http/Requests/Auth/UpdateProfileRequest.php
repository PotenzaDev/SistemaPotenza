<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'current_password' => ['nullable', 'string'],
            'new_password'     => ['nullable', 'string', 'min:6'],
        ];
    }
}
