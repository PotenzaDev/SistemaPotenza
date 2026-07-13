<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ChamadaSuporte;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use Illuminate\Support\Collection;

class ChamadaSuporteService
{
    /** Chamado de suporte aberto pelo operário a partir da sua sessão de trabalho ativa. */
    public function solicitar(Operario $operario): ChamadaSuporte
    {
        $sessao = SessaoTrabalho::where('operario_id', $operario->id)
            ->where('status', SessaoTrabalho::STATUS_ATIVA)
            ->whereNull('fim')
            ->first();

        if (! $sessao) {
            throw new BusinessException('Nenhuma sessão ativa encontrada.', 422);
        }

        return ChamadaSuporte::create([
            'sessao_trabalho_id' => $sessao->id,
            'maquina_id'         => $sessao->maquina_id,
            'operario_id'        => $operario->id,
            'origem'             => 'operario',
        ]);
    }

    /** Chamado de suporte aberto pela manutenção (sem sessão/máquina associada). */
    public function solicitarManutencao(): ChamadaSuporte
    {
        return ChamadaSuporte::create([
            'origem' => 'manutencao',
        ]);
    }

    /** Chamados ainda não vistos pelo admin, mais recentes primeiro. */
    public function listarPendentes(): Collection
    {
        return ChamadaSuporte::with(['maquina', 'operario'])
            ->whereNull('visualizado_em')
            ->orderByDesc('created_at')
            ->get();
    }

    public function marcarVisualizada(ChamadaSuporte $chamada): void
    {
        $chamada->update(['visualizado_em' => now()]);
    }
}
