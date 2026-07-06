<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFichaCabecoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maquina_id' => ['nullable', 'integer', 'exists:maquinas,id'],
            'operario_id' => ['nullable', 'integer', 'exists:operarios,id'],
            'data' => ['nullable', 'date'],
            'top_esquerdo_mm' => ['nullable', 'numeric', 'min:0'],
            'top_direito_mm' => ['nullable', 'numeric', 'min:0'],
            'quantidade_pecas_vez' => ['nullable', 'integer', 'min:1'],
            'velocidade_trabalho' => ['nullable', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string'],

            'posicoes_cabecote' => ['sometimes', 'array'],
            'posicoes_cabecote.*.cabecote' => ['required', 'string', 'max:50'],
            'posicoes_cabecote.*.sentido' => ['required', 'in:inferior,superior,horizontal'],
            'posicoes_cabecote.*.largura_mm' => ['required', 'numeric', 'min:0'],
            'posicoes_cabecote.*.deslocamento_mm' => ['required', 'numeric', 'min:0'],
            'posicoes_cabecote.*.altura_cabecote_mm' => ['required', 'numeric', 'min:0'],
            'posicoes_cabecote.*.obs' => ['nullable', 'string', 'max:255'],

            'posicoes_broca' => ['sometimes', 'array'],
            'posicoes_broca.*.cabecote' => ['required', 'string', 'max:50'],
            'posicoes_broca.*.sentido' => ['required', 'in:inferior,superior,horizontal'],
            'posicoes_broca.*.posicao' => ['required', 'string', 'max:50'],
            'posicoes_broca.*.broca_id' => ['required', 'integer', 'exists:brocas,id'],
            'posicoes_broca.*.passante' => ['required', 'boolean'],
            'posicoes_broca.*.profundidade_mm' => ['nullable', 'numeric', 'min:0'],
            'posicoes_broca.*.agregado' => ['nullable', 'string', 'max:255'],
            'posicoes_broca.*.obs' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            foreach ($this->input('posicoes_broca', []) as $i => $linha) {
                $passante = filter_var($linha['passante'] ?? true, FILTER_VALIDATE_BOOLEAN);

                if (! $passante && ($linha['profundidade_mm'] ?? null) === null) {
                    $validator->errors()->add(
                        "posicoes_broca.{$i}.profundidade_mm",
                        'A profundidade é obrigatória quando a broca não é passante.'
                    );
                }
            }
        });
    }
}
