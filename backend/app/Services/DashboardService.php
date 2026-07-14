<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Apontamento;
use App\Models\Maquina;
use App\Models\SessaoTrabalho;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function resumo(): array
    {
        $hoje = Carbon::today();

        return [
            'kpis'              => $this->kpis($hoje),
            'maquinas'          => $this->estadoMaquinas(),
            'producao_por_hora' => $this->producaoPorHora($hoje),
            'pausas_por_motivo' => $this->pausasPorMotivo($hoje),
        ];
    }

    private function kpis(Carbon $hoje): array
    {
        $pecasHoje = DB::table('fichas_apontamento')
            ->join('apontamentos', 'fichas_apontamento.apontamento_id', '=', 'apontamentos.id')
            ->whereDate('apontamentos.producao_fim', $hoje)
            ->where('apontamentos.status', Apontamento::STATUS_FINALIZADO)
            ->sum('fichas_apontamento.qtd_produzida');

        $apontamentosHoje = Apontamento::where('status', Apontamento::STATUS_FINALIZADO)
            ->whereDate('producao_fim', $hoje)
            ->count();

        $maquinasAtivas = SessaoTrabalho::whereNull('fim')->count();

        $pausasHoje = DB::table('pausas')
            ->whereDate('inicio', $hoje)
            ->whereNotNull('fim')
            ->sum('duracao_segundos');

        return [
            'pecas_hoje'                    => (int) $pecasHoje,
            'apontamentos_finalizados_hoje' => $apontamentosHoje,
            'maquinas_ativas'               => $maquinasAtivas,
            'total_pausa_minutos_hoje'      => (int) round($pausasHoje / 60),
        ];
    }

    private function estadoMaquinas(): array
    {
        $maquinas = Maquina::where('ativa', true)
            ->with([
                'sessaoAtiva.operario.user',
                'sessaoAtiva.pausaOciosaAberta',
                'sessaoAtiva.apontamentos' => fn ($q) => $q
                    ->whereIn('status', [
                        Apontamento::STATUS_EM_SETUP,
                        Apontamento::STATUS_AGUARDANDO_PRODUCAO,
                        Apontamento::STATUS_EM_PRODUCAO,
                        Apontamento::STATUS_EM_PAUSA_SETUP,
                        Apontamento::STATUS_EM_PAUSA_AGUARDANDO,
                        Apontamento::STATUS_EM_PAUSA_PRODUCAO,
                    ]),
            ])
            ->orderBy('nome')
            ->get();

        return $maquinas->map(function (Maquina $maquina) {
            $sessao      = $maquina->sessaoAtiva;
            $apontamento = $sessao?->apontamentos->first();
            $pausaOciosa = $apontamento === null ? $sessao?->pausaOciosaAberta : null;

            return [
                'id'                   => $maquina->id,
                'nome'                 => $maquina->nome,
                'status'               => $apontamento?->status ?? ($pausaOciosa ? 'pausa_ociosa' : 'livre'),
                'operario'             => $sessao?->operario?->user?->name,
                'lote'                 => $apontamento?->ordem_lote,
                'cod_peca'             => $apontamento?->cod_peca,
                'desc_peca'            => $apontamento?->desc_peca,
                'qtde_total'           => $apontamento?->qtde_total,
                'setup_duracao_min'    => $apontamento?->setup_duracao_segundos !== null
                    ? (int) round($apontamento->setup_duracao_segundos / 60) : null,
                'producao_duracao_min' => $apontamento?->producao_duracao_segundos !== null
                    ? (int) round($apontamento->producao_duracao_segundos / 60) : null,
                'total_pausa_min'      => $apontamento?->total_pausa_segundos !== null
                    ? (int) round($apontamento->total_pausa_segundos / 60) : null,
                'inicio'               => $apontamento?->setup_inicio?->format('H:i')
                    ?? $pausaOciosa?->inicio?->format('H:i'),
            ];
        })->values()->all();
    }

    private function producaoPorHora(Carbon $hoje): array
    {
        $rows = DB::table('fichas_apontamento')
            ->selectRaw('EXTRACT(HOUR FROM bipada_at)::integer as hora, SUM(qtd_produzida) as pecas')
            ->whereDate('bipada_at', $hoje)
            ->whereNotNull('qtd_produzida')
            ->groupByRaw('EXTRACT(HOUR FROM bipada_at)')
            ->orderByRaw('EXTRACT(HOUR FROM bipada_at)')
            ->get();

        return $rows->map(fn ($r) => [
            'hora'  => sprintf('%02d:00', $r->hora),
            'pecas' => (int) $r->pecas,
        ])->values()->all();
    }

    private function pausasPorMotivo(Carbon $hoje): array
    {
        $rows = DB::table('pausas')
            ->join('motivos_pausa', 'pausas.motivo_pausa_id', '=', 'motivos_pausa.id')
            ->selectRaw('motivos_pausa.nome as motivo, SUM(pausas.duracao_segundos) as total_segundos')
            ->whereDate('pausas.inicio', $hoje)
            ->whereNotNull('pausas.fim')
            ->groupBy('motivos_pausa.nome')
            ->orderByRaw('SUM(pausas.duracao_segundos) DESC')
            ->get();

        return $rows->map(fn ($r) => [
            'motivo'    => $r->motivo,
            'total_min' => (int) round($r->total_segundos / 60),
        ])->values()->all();
    }
}
