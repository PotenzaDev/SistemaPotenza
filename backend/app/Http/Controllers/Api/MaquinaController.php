<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ConfiguracaoCabecoteMaquina;
use App\Models\Maquina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaquinaController extends Controller
{
    use ApiResponseTrait;

    private const CAMPOS_CABECOTE = [
        'cabecotes_inferiores',
        'cabecotes_superiores',
        'cabecotes_topo',
        'cabecotes_traseiros',
        'pinos_por_cabecote',
    ];

    private const RELACOES = ['etapaFluxo', 'configuracaoCabecote'];

    public function index(): JsonResponse
    {
        return $this->successResponse(Maquina::with(self::RELACOES)->orderBy('nome')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'etapa_fluxo_id' => ['required', 'integer', 'exists:etapas_fluxo,id'],
            'nome' => ['required', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50', 'unique:maquinas,codigo'],
            'ano' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'descricao' => ['nullable', 'string'],
            'ativa' => ['boolean'],
            'foto' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'cabecotes_inferiores' => ['nullable', 'integer', 'min:0'],
            'cabecotes_superiores' => ['nullable', 'integer', 'min:0'],
            'cabecotes_topo' => ['nullable', 'integer', 'min:0'],
            'cabecotes_traseiros' => ['nullable', 'integer', 'min:0'],
            'pinos_por_cabecote' => ['nullable', 'integer', 'min:0'],
        ]);

        $cabecote = Arr::only($data, self::CAMPOS_CABECOTE);
        $data = Arr::except($data, self::CAMPOS_CABECOTE);

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('maquinas', 'public');
        }

        $maquina = DB::transaction(function () use ($data, $cabecote) {
            $maquina = Maquina::create($data);
            $this->salvarConfiguracaoCabecote($maquina, $cabecote);

            return $maquina;
        });

        return $this->successResponse(
            $maquina->load(self::RELACOES),
            'Máquina criada.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $maquina = Maquina::with(self::RELACOES)->find($id);

        return $maquina
            ? $this->successResponse($maquina)
            : $this->errorResponse('Máquina não encontrada.', 404);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $maquina = Maquina::find($id);

        if (! $maquina) {
            return $this->errorResponse('Máquina não encontrada.', 404);
        }

        $data = $request->validate([
            'etapa_fluxo_id' => ['sometimes', 'integer', 'exists:etapas_fluxo,id'],
            'nome' => ['sometimes', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50', 'unique:maquinas,codigo,'.$id],
            'ano' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'descricao' => ['nullable', 'string'],
            'ativa' => ['boolean'],
            'foto' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'cabecotes_inferiores' => ['nullable', 'integer', 'min:0'],
            'cabecotes_superiores' => ['nullable', 'integer', 'min:0'],
            'cabecotes_topo' => ['nullable', 'integer', 'min:0'],
            'cabecotes_traseiros' => ['nullable', 'integer', 'min:0'],
            'pinos_por_cabecote' => ['nullable', 'integer', 'min:0'],
        ]);

        $cabecote = Arr::only($data, self::CAMPOS_CABECOTE);
        $data = Arr::except($data, self::CAMPOS_CABECOTE);

        if ($request->hasFile('foto')) {
            if ($maquina->foto) {
                Storage::disk('public')->delete($maquina->foto);
            }
            $data['foto'] = $request->file('foto')->store('maquinas', 'public');
        }

        DB::transaction(function () use ($maquina, $data, $cabecote) {
            $maquina->update($data);
            $this->salvarConfiguracaoCabecote($maquina->fresh(), $cabecote);
        });

        return $this->successResponse($maquina->fresh()->load(self::RELACOES), 'Máquina atualizada.');
    }

    public function destroy(int $id): JsonResponse
    {
        $maquina = Maquina::find($id);

        if (! $maquina) {
            return $this->errorResponse('Máquina não encontrada.', 404);
        }

        $maquina->update(['ativa' => false]);

        return $this->successResponse($maquina->load(self::RELACOES), 'Máquina desativada.');
    }

    private function salvarConfiguracaoCabecote(Maquina $maquina, array $cabecote): void
    {
        if (! $maquina->etapaFluxo->requer_config_cabecote || $cabecote === []) {
            return;
        }

        ConfiguracaoCabecoteMaquina::updateOrCreate(
            ['maquina_id' => $maquina->id],
            $cabecote
        );
    }
}
