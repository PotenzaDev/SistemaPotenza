<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Rotina;
use Illuminate\Foundation\Http\FormRequest;

class CreateRotinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'      => ['required', 'string', 'max:100'],
            'slug'      => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'unique:rotinas,slug'],
            'pagina'    => [
                $this->filled('parent_id') ? 'required' : 'nullable',
                'string',
                'max:255',
                'starts_with:/',
            ],
            'icone'     => ['required', 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z0-9]*$/'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:rotinas,id',
                function (string $attribute, mixed $value, callable $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $parent = Rotina::find($value);

                    if ($parent && ! $parent->isPai()) {
                        $fail('A rotina pai não pode ser uma sub-rotina.');
                    }
                },
            ],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required'      => 'O nome é obrigatório.',
            'slug.required'      => 'O slug é obrigatório.',
            'slug.regex'         => 'O slug deve conter apenas letras minúsculas, números e underline.',
            'slug.unique'        => 'Este slug já está em uso.',
            'pagina.required'    => 'A página é obrigatória para sub-rotinas.',
            'pagina.starts_with' => 'A página deve começar com "/".',
            'icone.required'     => 'O ícone é obrigatório.',
            'icone.regex'        => 'Ícone inválido — use o nome do componente lucide-react (ex: Users).',
            'parent_id.exists'   => 'Rotina pai inválida.',
        ];
    }
}
