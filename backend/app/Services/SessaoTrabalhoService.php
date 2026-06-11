<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Apontamento;
use App\Models\MotivoPausa;
use App\Models\Operario;
use App\Models\Pausa;
use App\Models\SessaoTrabalho;
use App\Models\Turno;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use Carbon\Carbon;

class SessaoTrabalhoService
{
    public function __construct(
        private readonly SessaoTrabalhoRepositoryInterface $sessaoRepo,
        private readonly ApontamentoRepositoryInterface    $apontamentoRepo,
    ) {}

    public function iniciar(Operario $operario, int $maquinaId): SessaoTrabalho
    {
        if (! Turno::doDia(Carbon::now()->dayOfWeekIso)) {
            throw new BusinessException('Nenhum turno configurado para hoje. Não é possível iniciar.', 422);
        }

        $maquina = \App\Models\Maquina::where('id', $maquinaId)->where('ativa', true)->first();

        if (! $maquina) {
            throw new BusinessException('Máquina não encontrada ou inativa.', 422);
        }

        $sessaoInterrompida = $this->sessaoRepo->buscarSessaoInterrompida($operario->id, $maquinaId);

        if ($sessaoInterrompida) {
            $this->sessaoRepo->encerrarSessoesAtivas($operario);

            return $this->sessaoRepo->reabrirSessao($sessaoInterrompida)->load(['maquina.etapaFluxo']);
        }

        $this->sessaoRepo->encerrarSessoesAtivas($operario);

        $sessao = $this->sessaoRepo->criarSessao($operario->id, $maquinaId);

        // Se há apontamento pausado na mesma máquina (de turno anterior), reatribui à nova sessão.
        $pendente = $this->apontamentoRepo->buscarApontamentoPendentePorMaquina($maquinaId, $operario->id);

        if ($pendente) {
            $this->apontamentoRepo->atualizarSessao($pendente, $sessao->id);
        }

        return $sessao->load(['maquina.etapaFluxo']);
    }

    public function encerrar(Operario $operario, bool $fimTurno = false): void
    {
        $sessao = $this->sessaoRepo->buscarSessaoAtiva($operario);

        if (! $sessao) {
            throw new BusinessException('Nenhuma sessão ativa encontrada.', 422);
        }

        $this->autoPausarApontamentoAtivo($sessao);

        $this->sessaoRepo->encerrarSessao($sessao, $fimTurno);
    }

    public function encerrarTurno(Operario $operario): void
    {
        $turno = Turno::doDia(Carbon::now()->dayOfWeekIso);

        if (! $turno) {
            throw new BusinessException('Nenhum turno configurado para hoje.', 422);
        }

        $liberadoApartirDe = Carbon::now()->copy()
            ->setTimeFromTimeString($turno->hora_fim)
            ->subMinutes($turno->tolerancia_finalizacao_minutos);

        if (Carbon::now()->lessThan($liberadoApartirDe)) {
            throw new BusinessException(
                "Só é possível finalizar o turno a partir das {$liberadoApartirDe->format('H:i')}.",
                422
            );
        }

        $this->encerrar($operario, true);
    }

    public function ativa(Operario $operario): ?SessaoTrabalho
    {
        return $this->sessaoRepo->buscarSessaoAtiva($operario);
    }

    /**
     * Se há um apontamento em execução (em_setup ou em_producao), cria pausa automática
     * de "Fim de Turno" para que o tempo ocioso entre turnos não seja contado.
     */
    public function autoPausarApontamentoAtivo(SessaoTrabalho $sessao): void
    {
        $apontamento = $this->apontamentoRepo->buscarApontamentoAtivo($sessao);

        if (! $apontamento) {
            return;
        }

        $statusPausaveis = [Apontamento::STATUS_EM_SETUP, Apontamento::STATUS_EM_PRODUCAO];

        if (! in_array($apontamento->status, $statusPausaveis, true)) {
            return;
        }

        $motivo = MotivoPausa::where('nome', 'Fim de Turno')->where('is_sistema', true)->first();

        if (! $motivo) {
            return;
        }

        $fase = $apontamento->status === Apontamento::STATUS_EM_SETUP ? 'setup' : 'producao';

        Pausa::create([
            'apontamento_id'  => $apontamento->id,
            'motivo_pausa_id' => $motivo->id,
            'fase'            => $fase,
            'inicio'          => Carbon::now(),
        ]);

        $novoStatus = $fase === 'setup'
            ? Apontamento::STATUS_EM_PAUSA_SETUP
            : Apontamento::STATUS_EM_PAUSA_PRODUCAO;

        $apontamento->update(['status' => $novoStatus]);
    }
}
