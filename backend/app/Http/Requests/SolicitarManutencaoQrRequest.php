<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarManutencaoQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'solicitante' => ['required', 'string', 'max:150'],
            'motivo' => ['required', 'string'],
            'prioridade' => ['required', 'in:baixa,normal,alta,critica'],
        ];
    }
}
