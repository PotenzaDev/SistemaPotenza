<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrdemManutencaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:aberta,em_atendimento,pausada,concluida,cancelada'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
