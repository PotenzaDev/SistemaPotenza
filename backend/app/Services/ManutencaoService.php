<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\OrdemManutencao;
use App\Models\PecaOrdemManutencao;
use App\Models\ServicoOrdemManutencao;
use Illuminate\Support\Collection;

class ManutencaoService
{
    private const RELACOES = ['maquina.etapaFluxo', 'pecas', 'servicos'];

    /**
     * Lista ordens de manutenção para o admin, com filtros opcionais de
     * status, data de solicitação e etapa do fluxo (via máquina).
     */
    public function listar(array $filtros): Collection
    {
        $query = OrdemManutencao::with(self::RELACOES);

        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }
        if (! empty($filtros['data'])) {
            $query->whereDate('solicitado_em', $filtros['data']);
        }
        if (! empty($filtros['etapa_fluxo_id'])) {
            $query->whereHas('maquina', fn ($q) => $q->where('etapa_fluxo_id', $filtros['etapa_fluxo_id']));
        }

        return $query->orderByDesc('solicitado_em')->get();
    }

    /**
     * Cria uma nova solicitação de manutenção (OS) com status inicial "aberta".
     */
    public function criarSolicitacao(array $data): OrdemManutencao
    {
        return OrdemManutencao::create([
            ...$data,
            'status' => 'aberta',
            'solicitado_em' => now(),
        ]);
    }

    /**
     * Atualiza status/observações de uma OS, preenchendo automaticamente
     * os timestamps de atendimento/conclusão conforme a transição de status.
     */
    public function atualizarStatus(OrdemManutencao $ordem, array $data): OrdemManutencao
    {
        if (isset($data['status'])) {
            if ($data['status'] === 'em_atendimento' && is_null($ordem->atendido_em)) {
                $data['atendido_em'] = now();
            }
            if ($data['status'] === 'concluida') {
                $data['concluido_em'] = now();
            }
        }

        $ordem->update($data);

        return $ordem->fresh();
    }

    public function adicionarPeca(OrdemManutencao $ordem, array $data): OrdemManutencao
    {
        PecaOrdemManutencao::create([
            'ordem_manutencao_id' => $ordem->id,
            ...$data,
        ]);

        return $ordem->fresh();
    }

    public function removerPeca(OrdemManutencao $ordem, int $pecaId): OrdemManutencao
    {
        $peca = PecaOrdemManutencao::where('ordem_manutencao_id', $ordem->id)->find($pecaId);

        if (! $peca) {
            throw new BusinessException('Peça não encontrada.', 404);
        }

        $peca->delete();

        return $ordem->fresh();
    }

    public function adicionarServico(OrdemManutencao $ordem, array $data): OrdemManutencao
    {
        ServicoOrdemManutencao::create([
            'ordem_manutencao_id' => $ordem->id,
            ...$data,
        ]);

        return $ordem->fresh();
    }

    public function removerServico(OrdemManutencao $ordem, int $servicoId): OrdemManutencao
    {
        $servico = ServicoOrdemManutencao::where('ordem_manutencao_id', $ordem->id)->find($servicoId);

        if (! $servico) {
            throw new BusinessException('Serviço não encontrado.', 404);
        }

        $servico->delete();

        return $ordem->fresh();
    }
}
