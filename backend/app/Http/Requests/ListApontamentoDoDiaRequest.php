<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListApontamentoDoDiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim'    => ['nullable', 'date_format:Y-m-d'],
            'operario_id' => ['nullable', 'integer', 'exists:operarios,id'],
            'maquina_id'  => ['nullable', 'integer', 'exists:maquinas,id'],
            'ordem_lote'  => ['nullable', 'string'],
        ];
    }
}
