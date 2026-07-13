<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PausarSessaoOciosaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_pausa_id' => ['required', 'integer', 'exists:motivos_pausa,id'],
        ];
    }
}
