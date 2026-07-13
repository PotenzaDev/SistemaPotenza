<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConfiguracaoCabecoteMaquina;
use App\Models\Maquina;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaquinaService
{
    private const CAMPOS_CABECOTE = [
        'cabecotes_inferiores',
        'cabecotes_superiores',
        'cabecotes_topo',
        'cabecotes_traseiros',
        'pinos_por_cabecote',
    ];

    public function criar(array $data, ?UploadedFile $foto): Maquina
    {
        [$data, $cabecote] = $this->separarCabecote($data);

        if ($foto) {
            $data['foto'] = $foto->store('maquinas', 'public');
        }

        return DB::transaction(function () use ($data, $cabecote) {
            $maquina = Maquina::create($data);
            $this->salvarConfiguracaoCabecote($maquina, $cabecote);

            return $maquina;
        });
    }

    public function atualizar(Maquina $maquina, array $data, ?UploadedFile $foto): Maquina
    {
        [$data, $cabecote] = $this->separarCabecote($data);

        if ($foto) {
            if ($maquina->foto) {
                Storage::disk('public')->delete($maquina->foto);
            }
            $data['foto'] = $foto->store('maquinas', 'public');
        }

        DB::transaction(function () use ($maquina, $data, $cabecote) {
            $maquina->update($data);
            $this->salvarConfiguracaoCabecote($maquina->fresh(), $cabecote);
        });

        return $maquina->fresh();
    }

    private function separarCabecote(array $data): array
    {
        return [
            Arr::except($data, self::CAMPOS_CABECOTE),
            Arr::only($data, self::CAMPOS_CABECOTE),
        ];
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
