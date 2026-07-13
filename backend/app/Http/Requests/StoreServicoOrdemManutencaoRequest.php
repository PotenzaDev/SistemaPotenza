<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicoOrdemManutencaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servico' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
            'valor' => ['required', 'numeric', 'min:0'],
            'data' => ['required', 'date'],
        ];
    }
}
