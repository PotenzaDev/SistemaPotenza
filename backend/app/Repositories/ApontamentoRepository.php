<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Apontamento;
use App\Models\SessaoTrabalho;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ApontamentoRepository implements ApontamentoRepositoryInterface
{
    private const EAGER = ['etapaFluxo', 'fichas', 'pausas.motivoPausa'];

    private const STATUS_ATIVOS = [
        Apontamento::STATUS_EM_SETUP,
        Apontamento::STATUS_AGUARDANDO_PRODUCAO,
        Apontamento::STATUS_EM_PRODUCAO,
        Apontamento::STATUS_EM_PAUSA_SETUP,
        Apontamento::STATUS_EM_PAUSA_AGUARDANDO,
        Apontamento::STATUS_EM_PAUSA_PRODUCAO,
    ];

    public function criar(array $dados): Apontamento
    {
        return Apontamento::create($dados)->load(self::EAGER);
    }

    public function buscarApontamentoAtivo(SessaoTrabalho $sessao): ?Apontamento
    {
        return Apontamento::where('sessao_trabalho_id', $sessao->id)
            ->whereIn('status', self::STATUS_ATIVOS)
            ->with(self::EAGER)
            ->first();
    }

    public function buscarApontamentosAtivos(SessaoTrabalho $sessao): Collection
    {
        return Apontamento::where('sessao_trabalho_id', $sessao->id)
            ->whereIn('status', self::STATUS_ATIVOS)
            ->with(self::EAGER)
            ->get();
    }

    public function somarQtdProduzida(int $etapaFluxoId, string $ordemLote): int
    {
        return (int) Apontamento::where('etapa_fluxo_id', $etapaFluxoId)
            ->where('ordem_lote', $ordemLote)
            ->where('status', 'finalizado')
            ->sum('qtd_produzida');
    }

    /**
     * Apontamentos relevantes para o período informado, ou — na ausência de
     * filtros de data — os de hoje (iniciados hoje OU ainda em aberto, já que
     * uma produção pode atravessar a virada do dia e terminar amanhã).
     *
     * Filtros aceitos (todos opcionais): data_inicio, data_fim (Y-m-d),
     * operario_id, maquina_id, grupo_id, ordem_lote.
     */
    public function resolverPeriodo(array $filtros = []): array
    {
        $dataInicio = $filtros['data_inicio'] ?? null;
        $dataFim    = $filtros['data_fim'] ?? null;

        if ($dataInicio || $dataFim) {
            return [
                'inicio' => Carbon::parse($dataInicio ?? $dataFim)->startOfDay(),
                'fim'    => Carbon::parse($dataFim ?? $dataInicio)->endOfDay(),
            ];
        }

        return [
            'inicio' => Carbon::today()->startOfDay(),
            'fim'    => Carbon::today()->endOfDay(),
        ];
    }

    public function apontamentosDoDia(array $filtros = []): Collection
    {
        ['inicio' => $inicio, 'fim' => $fim] = $this->resolverPeriodo($filtros);

        $query = Apontamento::query()
            // Interseção de intervalos: aparece no dia se estava ativo em qualquer momento dele.
            // COALESCE: máquinas com possui_setup=false não têm setup_inicio, então usa-se
            // producao_inicio (ou, no limite, created_at) como âncora de início.
            ->whereRaw('COALESCE(setup_inicio, producao_inicio, created_at) <= ?', [$fim])
            ->where(function ($q) use ($inicio) {
                $q->whereNull('producao_fim')
                  ->orWhere('producao_fim', '>=', $inicio);
            });

        if (! empty($filtros['operario_id']) || ! empty($filtros['maquina_id']) || ! empty($filtros['grupo_id'])) {
            // withTrashed(): apontamentos finalizados de uma sessão cancelada
            // (soft-deleted) continuam contando no relatório/histórico.
            $query->whereHas('sessaoTrabalho', function ($q) use ($filtros) {
                $q->withTrashed();

                if (! empty($filtros['operario_id'])) {
                    $q->where('operario_id', $filtros['operario_id']);
                }
                if (! empty($filtros['maquina_id'])) {
                    $q->where('maquina_id', $filtros['maquina_id']);
                }
                if (! empty($filtros['grupo_id'])) {
                    $q->whereHas('maquina', fn ($mq) => $mq->where('etapa_fluxo_id', $filtros['grupo_id']));
                }
            });
        }

        if (! empty($filtros['ordem_lote'])) {
            $query->where('ordem_lote', 'like', '%' . $filtros['ordem_lote'] . '%');
        }

        return $query
            ->with([
                // withTrashed(): sem isso, o relacionamento eager-loaded volta
                // null para apontamentos de uma sessão cancelada (soft-deleted),
                // mesmo que o whereHas() acima já a inclua no resultado.
                'sessaoTrabalho' => fn ($q) => $q->withTrashed()->with(['operario.user', 'maquina.etapaFluxo']),
                'fichas',
                'pausas.motivoPausa',
            ])
            ->orderByRaw('COALESCE(setup_inicio, producao_inicio, created_at) DESC')
            ->get();
    }

    public function historicoPorOperario(int $operarioId): Collection
    {
        // withTrashed(): apontamentos finalizados de uma sessão cancelada
        // (soft-deleted) continuam aparecendo no histórico do operário.
        return Apontamento::whereHas(
            'sessaoTrabalho',
            fn ($q) => $q->withTrashed()->where('operario_id', $operarioId)
        )
            ->with(self::EAGER)
            ->latest()
            ->get();
    }

    public function buscarApontamentoPendentePorMaquina(int $maquinaId, int $operarioId): ?Apontamento
    {
        return Apontamento::whereIn('status', [
            Apontamento::STATUS_EM_PAUSA_SETUP,
            Apontamento::STATUS_EM_PAUSA_AGUARDANDO,
            Apontamento::STATUS_EM_PAUSA_PRODUCAO,
        ])
            ->where('created_at', '>=', Carbon::now()->subDays(3))
            ->whereHas('sessaoTrabalho', fn ($q) => $q
                ->where('maquina_id', $maquinaId)
                ->where('operario_id', $operarioId)
                ->whereNotNull('fim')
                // Sessões pausadas manualmente ficam fora: só são retomadas
                // por escolha explícita do operário (sessao_pausada_id),
                // nunca reatribuídas automaticamente para uma sessão nova.
                ->where('status', '!=', SessaoTrabalho::STATUS_PAUSADA)
            )
            ->whereHas('pausas', fn ($q) => $q->whereNull('fim'))
            ->with(self::EAGER)
            ->first();
    }

    public function atualizarSessao(Apontamento $apontamento, int $sessaoId): Apontamento
    {
        $apontamento->update(['sessao_trabalho_id' => $sessaoId]);

        return $apontamento->load(self::EAGER);
    }

    public function buscarUltimoFinalizadoPorLoteEtapa(string $ordemLote, string $codPeca, int $etapaFluxoId): ?Apontamento
    {
        return Apontamento::where('ordem_lote', $ordemLote)
            ->where('cod_peca', $codPeca)
            ->where('etapa_fluxo_id', $etapaFluxoId)
            ->where('status', Apontamento::STATUS_FINALIZADO)
            ->with(self::EAGER)
            ->latest()
            ->first();
    }

    public function excluirNaoFinalizadosPorSessao(SessaoTrabalho $sessao): int
    {
        return Apontamento::where('sessao_trabalho_id', $sessao->id)
            ->where('status', '!=', Apontamento::STATUS_FINALIZADO)
            ->delete();
    }
}
