<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConfiguracaoCabecoteMaquina;
use App\Models\Maquina;
use App\Models\RegraMaquina;
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

    private const CAMPOS_REGRAS = [
        'possui_setup',
        'possui_producao',
        'permite_multiplas_passagens',
        'limite_passagens',
        'permite_finalizacao_parcial',
        'permite_pecas_diferentes_lote',
    ];

    public function criar(array $data, ?UploadedFile $foto): Maquina
    {
        [$data, $cabecote, $regras] = $this->separarConfiguracoes($data);

        if ($foto) {
            $data['foto'] = $foto->store('maquinas', 'public');
        }

        return DB::transaction(function () use ($data, $cabecote, $regras) {
            $maquina = Maquina::create($data);
            $this->salvarConfiguracaoCabecote($maquina, $cabecote);
            $this->salvarRegrasMaquina($maquina, $regras);

            return $maquina;
        });
    }

    public function atualizar(Maquina $maquina, array $data, ?UploadedFile $foto): Maquina
    {
        [$data, $cabecote, $regras] = $this->separarConfiguracoes($data);

        if ($foto) {
            if ($maquina->foto) {
                Storage::disk('public')->delete($maquina->foto);
            }
            $data['foto'] = $foto->store('maquinas', 'public');
        }

        DB::transaction(function () use ($maquina, $data, $cabecote, $regras) {
            $maquina->update($data);
            $this->salvarConfiguracaoCabecote($maquina->fresh(), $cabecote);
            $this->salvarRegrasMaquina($maquina->fresh(), $regras);
        });

        return $maquina->fresh();
    }

    private function separarConfiguracoes(array $data): array
    {
        return [
            Arr::except($data, [...self::CAMPOS_CABECOTE, ...self::CAMPOS_REGRAS]),
            Arr::only($data, self::CAMPOS_CABECOTE),
            Arr::only($data, self::CAMPOS_REGRAS),
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

    private function salvarRegrasMaquina(Maquina $maquina, array $regras): void
    {
        RegraMaquina::updateOrCreate(
            ['maquina_id' => $maquina->id],
            $regras
        );
    }
}
