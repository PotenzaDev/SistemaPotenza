<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListSessoesPausadasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maquina_id' => ['required', 'integer', 'exists:maquinas,id'],
        ];
    }
}
