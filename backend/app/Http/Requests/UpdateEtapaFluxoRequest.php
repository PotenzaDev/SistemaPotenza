<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEtapaFluxoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'max:100'],
            'ordem' => ['sometimes', 'integer', 'min:1'],
            'ativa' => ['boolean'],
        ];
    }
}
