import { useEffect, useState } from 'react'
import axios from 'axios'
import { ClipboardList, Loader2 } from 'lucide-react'
import { getApontamentosDoDia, type ApontamentoDoDia, type ApontamentosDoDia, type ApontamentoFiltros } from '@/api/apontamentos'
import { ApontamentosFiltro } from '@/components/ApontamentosFiltro'
import { ApontamentoDetalheModal } from '@/components/ApontamentoDetalheModal'
import { STATUS_LABEL, fmtDuracao, fmtHora } from '@/lib/apontamentoFormat'

function hoje(): string {
  return new Date().toISOString().slice(0, 10)
}

export function ApontamentosPage() {
  const [filtros, setFiltros] = useState<ApontamentoFiltros>(() => ({ dataInicio: hoje(), dataFim: hoje() }))
  const [dados, setDados]     = useState<ApontamentosDoDia | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState<string | null>(null)
  const [selecionado, setSelecionado] = useState<ApontamentoDoDia | null>(null)

  useEffect(() => {
    const controller = new AbortController()

    setLoading(true)
    setError(null)

    getApontamentosDoDia(filtros, controller.signal)
      .then(setDados)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) {
          setError('Não foi possível carregar os apontamentos.')
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })

    return () => controller.abort()
  }, [filtros])

  const apontamentos = dados?.apontamentos ?? []

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <ClipboardList className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Apontamentos</h1>
            <p className="text-sm text-slate-400">Filtre por período, operário, máquina ou lote</p>
          </div>
        </div>
        {!loading && !error && (
          <span className="text-xs text-slate-500 bg-white/5 px-3 py-1 rounded-full">
            {apontamentos.length} {apontamentos.length === 1 ? 'apontamento' : 'apontamentos'}
          </span>
        )}
      </div>

      <ApontamentosFiltro value={filtros} onChange={setFiltros} />

      {loading && (
        <div className="flex items-center justify-center gap-2 py-16 text-slate-400">
          <Loader2 className="w-5 h-5 animate-spin" />
          <span className="text-sm">Carregando…</span>
        </div>
      )}
      {error && (
        <div className="flex items-center justify-center py-16">
          <p className="text-sm text-red-400">{error}</p>
        </div>
      )}
      {!loading && !error && apontamentos.length === 0 && (
        <div className="flex items-center justify-center py-16">
          <p className="text-sm text-slate-500">Nenhum apontamento encontrado para os filtros selecionados.</p>
        </div>
      )}

      {!loading && !error && apontamentos.length > 0 && dados && (
        <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left">
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Operário</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Máquina</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Ordem / Lote</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Início</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Término</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">Peças</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">Pilhas</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">Tempo Setup</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">Tempo Produção</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">Pausas</th>
                <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {apontamentos.map((apontamento: ApontamentoDoDia) => {
                const s = STATUS_LABEL[apontamento.status] ?? { label: apontamento.status, color: 'text-slate-400' }
                const termino = apontamento.producao_fim ?? apontamento.setup_fim
                return (
                  <tr
                    key={apontamento.id}
                    onClick={() => setSelecionado(apontamento)}
                    className="hover:bg-white/[0.02] transition-colors cursor-pointer"
                  >
                    <td className="px-6 py-3 text-white">{apontamento.operario ?? '—'}</td>
                    <td className="px-6 py-3 text-slate-300">{apontamento.maquina ?? '—'}</td>
                    <td className="px-6 py-3">
                      <span className="font-mono text-white">{apontamento.ordem_lote}</span>
                      <span className="block text-xs text-slate-500">{apontamento.cod_peca}</span>
                    </td>
                    <td className="px-6 py-3 text-slate-300">{fmtHora(apontamento.setup_inicio)}</td>
                    <td className="px-6 py-3 text-slate-300">{fmtHora(termino)}</td>
                    <td className="px-6 py-3 text-right text-slate-300">{apontamento.qtd_pecas}</td>
                    <td className="px-6 py-3 text-right text-slate-300">{apontamento.qtd_pilhas}</td>
                    <td className="px-6 py-3 text-right text-slate-300">{fmtDuracao(apontamento.tempo_setup_segundos)}</td>
                    <td className="px-6 py-3 text-right text-slate-300">{fmtDuracao(apontamento.tempo_producao_segundos)}</td>
                    <td className="px-6 py-3 text-right text-slate-300">{apontamento.numero_pausas}</td>
                    <td className={`px-6 py-3 font-medium ${s.color}`}>{s.label}</td>
                  </tr>
                )
              })}
            </tbody>
            <tfoot>
              <tr className="border-t border-white/10 bg-white/[0.02]">
                <td colSpan={5} className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Total do período</td>
                <td className="px-6 py-3 text-right font-semibold text-white">{dados.totais.qtd_pecas}</td>
                <td className="px-6 py-3 text-right font-semibold text-white">{dados.totais.qtd_pilhas}</td>
                <td colSpan={4}></td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}

      <ApontamentoDetalheModal resumo={selecionado} onClose={() => setSelecionado(null)} />
    </div>
  )
}
