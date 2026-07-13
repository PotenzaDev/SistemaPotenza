<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListTimelineMaquinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data'       => ['nullable', 'date_format:Y-m-d'],
            'maquina_id' => ['nullable', 'integer', 'exists:maquinas,id'],
            'grupo_id'   => ['nullable', 'integer', 'exists:etapas_fluxo,id'],
        ];
    }
}
