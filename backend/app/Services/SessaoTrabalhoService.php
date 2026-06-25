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
        private readonly ApontamentoService                $apontamentoService,
    ) {}

    public function iniciar(Operario $operario, int $maquinaId, ?int $sessaoPausadaId = null): SessaoTrabalho
    {
        if (! Turno::doDia(Carbon::now()->dayOfWeekIso)) {
            throw new BusinessException('Nenhum turno configurado para hoje. Não é possível iniciar.', 422);
        }

        $maquina = \App\Models\Maquina::where('id', $maquinaId)->where('ativa', true)->first();

        if (! $maquina) {
            throw new BusinessException('Máquina não encontrada ou inativa.', 422);
        }

        if ($sessaoPausadaId) {
            return $this->retomarSessaoPausada($operario, $maquinaId, $sessaoPausadaId);
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

    /**
     * Lista as sessões pausadas do operário na máquina informada, mais
     * recente primeiro, para a tela de escolha (retomar X vs. iniciar nova).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarSessoesPausadas(Operario $operario, int $maquinaId): array
    {
        return $this->sessaoRepo->listarSessoesPausadas($operario->id, $maquinaId)
            ->map(fn (SessaoTrabalho $sessao) => [
                'id'         => $sessao->id,
                'cod_peca'   => $sessao->apontamentoPausado?->cod_peca,
                'ordem_lote' => $sessao->apontamentoPausado?->ordem_lote,
                'desc_peca'  => $sessao->apontamentoPausado?->desc_peca,
                'pausada_em' => $sessao->fim?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Retoma uma sessão pausada específica, escolhida explicitamente pelo
     * operário (nunca de forma automática/implícita).
     */
    private function retomarSessaoPausada(Operario $operario, int $maquinaId, int $sessaoPausadaId): SessaoTrabalho
    {
        $sessao = $this->sessaoRepo->buscarSessaoPausadaPorId($sessaoPausadaId);

        if (! $sessao || $sessao->operario_id !== $operario->id || $sessao->maquina_id !== $maquinaId) {
            throw new BusinessException('Sessão pausada não encontrada ou já encerrada.', 404);
        }

        $this->sessaoRepo->encerrarSessoesAtivas($operario);

        $sessao = $this->sessaoRepo->reabrirSessaoPausada($sessao);

        $this->forcarRetomadaComNovoSetup($sessao);

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
     * Pausa a sessão ativa do operário: pausa o apontamento em execução (se houver)
     * com o motivo de sistema "Pausa de Sessão" e marca a sessão como pausada.
     */
    public function pausar(Operario $operario): SessaoTrabalho
    {
        $sessao = $this->sessaoRepo->buscarSessaoAtiva($operario);

        if (! $sessao) {
            throw new BusinessException('Nenhuma sessão ativa encontrada.', 422);
        }

        $this->autoPausarApontamentoAtivo($sessao, 'Pausa de Sessão');

        return $this->sessaoRepo->pausarSessao($sessao)->load(['maquina.etapaFluxo']);
    }

    /**
     * Se há um apontamento em execução (em_setup ou em_producao), cria pausa automática
     * com o motivo de sistema informado para que o tempo ocioso não seja contado.
     */
    public function autoPausarApontamentoAtivo(SessaoTrabalho $sessao, string $nomeMotivo = 'Fim de Turno'): void
    {
        $apontamento = $this->apontamentoRepo->buscarApontamentoAtivo($sessao);

        if (! $apontamento) {
            return;
        }

        $statusPausaveis = [Apontamento::STATUS_EM_SETUP, Apontamento::STATUS_EM_PRODUCAO];

        if (! in_array($apontamento->status, $statusPausaveis, true)) {
            return;
        }

        $motivo = MotivoPausa::where('nome', $nomeMotivo)->where('is_sistema', true)->first();

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

    /**
     * Após reabrir uma sessão pausada, força o apontamento que estava pausado
     * de volta para em_setup com uma nova janela de setup.
     */
    private function forcarRetomadaComNovoSetup(SessaoTrabalho $sessao): void
    {
        $apontamento = $this->apontamentoRepo->buscarApontamentoAtivo($sessao);

        if (! $apontamento) {
            return;
        }

        $statusPausados = [Apontamento::STATUS_EM_PAUSA_SETUP, Apontamento::STATUS_EM_PAUSA_PRODUCAO];

        if (! in_array($apontamento->status, $statusPausados, true)) {
            return;
        }

        $this->apontamentoService->retomarComNovoSetup($apontamento);
    }
}
