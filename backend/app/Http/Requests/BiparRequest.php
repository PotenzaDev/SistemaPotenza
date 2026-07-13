<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiparRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cod_peca'   => ['required', 'string'],
            'ordem_lote' => ['required', 'string'],
        ];
    }
}
