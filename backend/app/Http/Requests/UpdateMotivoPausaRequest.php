<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMotivoPausaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('motivos_pausa')?->id;

        return [
            'nome' => ['sometimes', 'string', 'max:100', 'unique:motivos_pausa,nome,' . $id],
            'ativo' => ['boolean'],
        ];
    }
}
