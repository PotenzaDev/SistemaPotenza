<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Rotina;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRotinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('rotina');

        return [
            'nome'      => ['sometimes', 'string', 'max:100'],
            'slug'      => ['sometimes', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'unique:rotinas,slug,' . $id],
            'pagina'    => [
                $this->filled('parent_id') ? 'required' : 'sometimes',
                'nullable',
                'string',
                'max:255',
                'starts_with:/',
            ],
            'icone'     => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z0-9]*$/'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:rotinas,id',
                function (string $attribute, mixed $value, callable $fail) use ($id): void {
                    if ($value === null) {
                        return;
                    }

                    if ((int) $value === (int) $id) {
                        $fail('Uma rotina não pode ser pai de si mesma.');

                        return;
                    }

                    $parent = Rotina::find($value);

                    if ($parent && ! $parent->isPai()) {
                        $fail('A rotina pai não pode ser uma sub-rotina.');
                    }
                },
            ],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex'         => 'O slug deve conter apenas letras minúsculas, números e underline.',
            'slug.unique'        => 'Este slug já está em uso.',
            'pagina.required'    => 'A página é obrigatória para sub-rotinas.',
            'pagina.starts_with' => 'A página deve começar com "/".',
            'icone.regex'        => 'Ícone inválido — use o nome do componente lucide-react (ex: Users).',
            'parent_id.exists'   => 'Rotina pai inválida.',
        ];
    }
}
