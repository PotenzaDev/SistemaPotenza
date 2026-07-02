<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\EventoSessao;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SessaoTrabalhoRepository implements SessaoTrabalhoRepositoryInterface
{
    public function criarSessao(int $operarioId, int $maquinaId): SessaoTrabalho
    {
        $sessao = SessaoTrabalho::create([
            'operario_id' => $operarioId,
            'maquina_id'  => $maquinaId,
            'inicio'      => Carbon::now(),
            'status'      => SessaoTrabalho::STATUS_ATIVA,
        ]);

        $this->registrarEvento($sessao->id, EventoSessao::TIPO_INICIO);

        return $sessao;
    }

    public function encerrarSessao(SessaoTrabalho $sessao, bool $fimTurno = false): SessaoTrabalho
    {
        $status = $fimTurno ? SessaoTrabalho::STATUS_INTERROMPIDA_TURNO : SessaoTrabalho::STATUS_ENCERRADA;

        $sessao->update(['fim' => Carbon::now(), 'fim_turno' => $fimTurno, 'status' => $status]);

        if ($fimTurno) {
            $this->registrarEvento($sessao->id, EventoSessao::TIPO_FIM_TURNO);
        }

        return $sessao->fresh();
    }

    public function buscarSessaoAtiva(Operario $operario): ?SessaoTrabalho
    {
        return SessaoTrabalho::where('operario_id', $operario->id)
            ->where('status', SessaoTrabalho::STATUS_ATIVA)
            ->with(['maquina.etapaFluxo'])
            ->first();
    }

    public function encerrarSessoesAtivas(Operario $operario): void
    {
        SessaoTrabalho::where('operario_id', $operario->id)
            ->where('status', SessaoTrabalho::STATUS_ATIVA)
            ->update(['fim' => Carbon::now(), 'status' => SessaoTrabalho::STATUS_ENCERRADA]);
    }

    public function buscarSessaoInterrompida(int $operarioId, int $maquinaId): ?SessaoTrabalho
    {
        return SessaoTrabalho::where('operario_id', $operarioId)
            ->where('maquina_id', $maquinaId)
            ->where('status', SessaoTrabalho::STATUS_INTERROMPIDA_TURNO)
            ->first();
    }

    public function reabrirSessao(SessaoTrabalho $sessao): SessaoTrabalho
    {
        $sessao->update(['fim' => null, 'status' => SessaoTrabalho::STATUS_ATIVA]);

        $this->registrarEvento($sessao->id, EventoSessao::TIPO_INICIO_TURNO);

        return $sessao->fresh();
    }

    public function pausarSessao(SessaoTrabalho $sessao): SessaoTrabalho
    {
        $sessao->update(['fim' => Carbon::now(), 'status' => SessaoTrabalho::STATUS_PAUSADA]);

        $this->registrarEvento($sessao->id, EventoSessao::TIPO_PAUSA_SESSAO);

        return $sessao->fresh();
    }

    public function listarSessoesPausadas(int $operarioId, int $maquinaId): Collection
    {
        return SessaoTrabalho::where('operario_id', $operarioId)
            ->where('maquina_id', $maquinaId)
            ->where('status', SessaoTrabalho::STATUS_PAUSADA)
            ->with('apontamentoPausado')
            ->orderByDesc('fim')
            ->get();
    }

    public function buscarSessaoPausadaPorId(int $id): ?SessaoTrabalho
    {
        return SessaoTrabalho::where('status', SessaoTrabalho::STATUS_PAUSADA)->find($id);
    }

    public function reabrirSessaoPausada(SessaoTrabalho $sessao): SessaoTrabalho
    {
        $sessao->update(['fim' => null, 'status' => SessaoTrabalho::STATUS_ATIVA]);

        $this->registrarEvento($sessao->id, EventoSessao::TIPO_RETOMADA_SESSAO);

        return $sessao->fresh();
    }

    public function registrarEvento(int $sessaoTrabalhoId, string $tipo, ?int $apontamentoId = null): void
    {
        EventoSessao::create([
            'sessao_trabalho_id' => $sessaoTrabalhoId,
            'apontamento_id'     => $apontamentoId,
            'tipo'               => $tipo,
            'ocorrido_em'        => Carbon::now(),
        ]);
    }

    public function cancelarSessao(SessaoTrabalho $sessao): void
    {
        $this->registrarEvento($sessao->id, EventoSessao::TIPO_CANCELAMENTO);

        $sessao->delete();
    }
}
