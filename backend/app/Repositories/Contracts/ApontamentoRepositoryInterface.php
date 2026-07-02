<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Apontamento;
use App\Models\SessaoTrabalho;
use Illuminate\Database\Eloquent\Collection;

interface ApontamentoRepositoryInterface
{
    public function criar(array $dados): Apontamento;

    public function buscarPorId(int $id): ?Apontamento;

    public function buscarApontamentoAtivo(SessaoTrabalho $sessao): ?Apontamento;

    public function somarQtdProduzida(int $etapaFluxoId, string $ordemLote): int;

    /**
     * Apontamentos iniciados hoje ou ainda em aberto (status ativo), ou — quando
     * filtros são informados — apontamentos no período/critérios solicitados.
     *
     * Filtros aceitos (todos opcionais): data_inicio, data_fim (Y-m-d),
     * operario_id, maquina_id, ordem_lote.
     */
    public function apontamentosDoDia(array $filtros = []): Collection;

    public function historicoPorOperario(int $operarioId): Collection;

    /** Busca apontamento em pausa (sessão encerrada) na máquina, do mesmo operário. */
    public function buscarApontamentoPendentePorMaquina(int $maquinaId, int $operarioId): ?Apontamento;

    /** Reatribui o apontamento a uma nova sessão de trabalho. */
    public function atualizarSessao(Apontamento $apontamento, int $sessaoId): Apontamento;

    /** Busca o apontamento finalizado mais recente para o lote/etapa. */
    public function buscarUltimoFinalizadoPorLoteEtapa(string $ordemLote, string $codPeca, int $etapaFluxoId): ?Apontamento;

    /**
     * Exclui definitivamente os apontamentos não finalizados da sessão (usado
     * ao cancelar uma sessão iniciada por engano). Apontamentos finalizados
     * são preservados. Retorna a quantidade de apontamentos excluídos.
     */
    public function excluirNaoFinalizadosPorSessao(SessaoTrabalho $sessao): int;
}
