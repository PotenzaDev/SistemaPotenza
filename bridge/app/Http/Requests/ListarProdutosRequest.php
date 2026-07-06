<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ListarProdutosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa' => ['required', 'string', 'in:FBM,FBP'],
            'nome' => ['nullable', 'string', 'max:255'],
            'sub_grupo' => ['nullable', 'string', 'max:255'],
            'data_corte' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $nome = $this->input('nome');
            $subGrupo = $this->input('sub_grupo');

            if (empty($nome) && empty($subGrupo)) {
                $validator->errors()->add(
                    'nome_ou_sub_grupo',
                    'Informe ao menos o nome ou o sub-grupo do produto.'
                );
            }
        });
    }
}
