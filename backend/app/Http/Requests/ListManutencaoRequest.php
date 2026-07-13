<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListManutencaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'         => ['nullable', 'string'],
            'data'           => ['nullable', 'date_format:Y-m-d'],
            'etapa_fluxo_id' => ['nullable', 'integer', 'exists:etapas_fluxo,id'],
        ];
    }
}
