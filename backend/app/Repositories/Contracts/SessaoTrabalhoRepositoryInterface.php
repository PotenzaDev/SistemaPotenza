<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Operario;
use App\Models\SessaoTrabalho;
use Illuminate\Support\Collection;

interface SessaoTrabalhoRepositoryInterface
{
    public function criarSessao(int $operarioId, int $maquinaId): SessaoTrabalho;

    public function encerrarSessao(SessaoTrabalho $sessao, bool $fimTurno = false): SessaoTrabalho;

    public function buscarSessaoAtiva(Operario $operario): ?SessaoTrabalho;

    public function encerrarSessoesAtivas(Operario $operario): void;

    public function buscarSessaoInterrompida(int $operarioId, int $maquinaId): ?SessaoTrabalho;

    public function reabrirSessao(SessaoTrabalho $sessao): SessaoTrabalho;

    /** Pausa a sessão (fim definido, status pausada) e registra o evento de pausa de sessão. */
    public function pausarSessao(SessaoTrabalho $sessao): SessaoTrabalho;

    /** @return Collection<int, SessaoTrabalho> Sessões pausadas do operário na máquina, mais recente primeiro. */
    public function listarSessoesPausadas(int $operarioId, int $maquinaId): Collection;

    public function buscarSessaoPausadaPorId(int $id): ?SessaoTrabalho;

    /** Reabre uma sessão pausada e registra o evento de retomada de sessão. */
    public function reabrirSessaoPausada(SessaoTrabalho $sessao): SessaoTrabalho;

    /** Registra um evento na linha do tempo da sessão, usado pelo SessaoCalculoService. */
    public function registrarEvento(int $sessaoTrabalhoId, string $tipo, ?int $apontamentoId = null): void;

    /** Soft-deleta a sessão (cancelamento) e registra o evento correspondente. */
    public function cancelarSessao(SessaoTrabalho $sessao): void;
}
