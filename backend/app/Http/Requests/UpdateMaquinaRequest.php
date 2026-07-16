<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaquinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('maquina')?->id;

        return [
            'etapa_fluxo_id' => ['sometimes', 'integer', 'exists:etapas_fluxo,id'],
            'nome' => ['sometimes', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50', 'unique:maquinas,codigo,' . $id],
            'ano' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'descricao' => ['nullable', 'string'],
            'ativa' => ['boolean'],
            'foto' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'cabecotes_inferiores' => ['nullable', 'integer', 'min:0'],
            'cabecotes_superiores' => ['nullable', 'integer', 'min:0'],
            'cabecotes_topo' => ['nullable', 'integer', 'min:0'],
            'cabecotes_traseiros' => ['nullable', 'integer', 'min:0'],
            'pinos_por_cabecote' => ['nullable', 'integer', 'min:0'],
            'possui_setup' => ['boolean'],
            'possui_producao' => ['boolean'],
            'permite_multiplas_passagens' => ['boolean'],
            'limite_passagens' => ['nullable', 'integer', 'min:2'],
            'permite_finalizacao_parcial' => ['boolean'],
            'permite_pecas_diferentes_lote' => ['boolean'],
        ];
    }
}
