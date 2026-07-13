<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ListRelatorioMaquinaRequest extends FormRequest
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
            'maquina_id'  => ['nullable', 'integer', 'exists:maquinas,id'],
            'grupo_id'    => ['nullable', 'integer', 'exists:etapas_fluxo,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $inicio = $this->input('data_inicio');
            $fim    = $this->input('data_fim');

            if ($inicio && $fim && $fim < $inicio) {
                $validator->errors()->add('data_fim', 'A data final deve ser maior ou igual à data inicial.');
            }
        });
    }
}
