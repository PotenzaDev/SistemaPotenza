<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Operario;
use App\Models\SessaoTrabalho;

interface SessaoTrabalhoRepositoryInterface
{
    public function criarSessao(int $operarioId, int $maquinaId): SessaoTrabalho;

    public function encerrarSessao(SessaoTrabalho $sessao, bool $fimTurno = false): SessaoTrabalho;

    public function buscarSessaoAtiva(Operario $operario): ?SessaoTrabalho;

    public function encerrarSessoesAtivas(Operario $operario): void;

    public function buscarSessaoInterrompida(int $operarioId, int $maquinaId): ?SessaoTrabalho;

    public function reabrirSessao(SessaoTrabalho $sessao): SessaoTrabalho;

    /** Registra um evento na linha do tempo da sessão, usado pelo SessaoCalculoService. */
    public function registrarEvento(int $sessaoTrabalhoId, string $tipo, ?int $apontamentoId = null): void;
}
