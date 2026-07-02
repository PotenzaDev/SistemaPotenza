import { useEffect, useState } from 'react'
import axios from 'axios'
import { Loader2, X } from 'lucide-react'
import { getApontamentosDoDia, type ApontamentoDoDia, type ApontamentosDoDia } from '@/api/apontamentos'
import type { RelatorioMaquinasFiltros } from '@/api/relatorios'
import { ApontamentoDetalheModal } from '@/components/ApontamentoDetalheModal'
import { STATUS_LABEL, fmtDuracao, fmtDataHora } from '@/lib/apontamentoFormat'

interface MaquinaSelecionada {
  maquina_id: number
  maquina: string
}

interface Props {
  maquina: MaquinaSelecionada | null
  filtros: RelatorioMaquinasFiltros
  onClose: () => void
}

export function ApontamentosMaquinaModal({ maquina, filtros, onClose }: Props) {
  const [dados, setDados]     = useState<ApontamentosDoDia | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError]     = useState<string | null>(null)
  const [selecionado, setSelecionado] = useState<ApontamentoDoDia | null>(null)

  useEffect(() => {
    if (!maquina) {
      setDados(null)
      setError(null)
      return
    }

    const controller = new AbortController()
    setLoading(true)
    setError(null)

    getApontamentosDoDia(
      { dataInicio: filtros.dataInicio, dataFim: filtros.dataFim, maquinaId: maquina.maquina_id },
      controller.signal,
    )
      .then(setDados)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar os apontamentos desta máquina.')
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })

    return () => controller.abort()
  }, [maquina, filtros.dataInicio, filtros.dataFim])

  if (!maquina) return null

  const apontamentos = dados?.apontamentos ?? []

  return (
    <>
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

        <div className="relative z-10 w-full max-w-5xl max-h-[90vh] overflow-y-auto bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl">
          <div className="flex items-start justify-between px-6 py-4 border-b border-white/5">
            <div>
              <h2 className="text-base font-semibold text-white">{maquina.maquina}</h2>
              <p className="text-sm text-slate-400">
                Apontamentos de {filtros.dataInicio} a {filtros.dataFim}
              </p>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
            >
              <X className="w-4 h-4" />
            </button>
          </div>

          <div className="px-6 py-5">
            {loading && (
              <div className="flex items-center justify-center gap-2 py-12 text-slate-400">
                <Loader2 className="w-5 h-5 animate-spin" />
                <span className="text-sm">Carregando…</span>
              </div>
            )}
            {error && (
              <div className="flex items-center justify-center py-12">
                <p className="text-sm text-red-400">{error}</p>
              </div>
            )}
            {!loading && !error && apontamentos.length === 0 && (
              <div className="flex items-center justify-center py-12">
                <p className="text-sm text-slate-500">Nenhum apontamento encontrado para esta máquina no período selecionado.</p>
              </div>
            )}

            {!loading && !error && apontamentos.length > 0 && dados && (
              <>
                <p className="text-xs text-slate-500 mb-3">
                  {apontamentos.length} {apontamentos.length === 1 ? 'apontamento' : 'apontamentos'} · {dados.totais.qtd_pecas} peças · {dados.totais.qtd_pilhas} pilhas
                </p>
                <div className="bg-white/[0.02] border border-white/5 rounded-lg overflow-hidden">
                  <table className="w-full text-xs">
                    <thead>
                      <tr className="text-left bg-white/[0.02]">
                        <th className="px-4 py-2 font-medium text-slate-400 uppercase tracking-wider">Operário</th>
                        <th className="px-4 py-2 font-medium text-slate-400 uppercase tracking-wider">Ordem / Lote</th>
                        <th className="px-4 py-2 font-medium text-slate-400 uppercase tracking-wider">Início</th>
                        <th className="px-4 py-2 font-medium text-slate-400 uppercase tracking-wider">Término</th>
                        <th className="px-4 py-2 font-medium text-slate-400 uppercase tracking-wider text-right">Peças</th>
                        <th className="px-4 py-2 font-medium text-slate-400 uppercase tracking-wider text-right">Pilhas</th>
                        <th className="px-4 py-2 font-medium text-slate-400 uppercase tracking-wider text-right">Setup</th>
                        <th className="px-4 py-2 font-medium text-slate-400 uppercase tracking-wider text-right">Produção</th>
                        <th className="px-4 py-2 font-medium text-slate-400 uppercase tracking-wider">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                      {apontamentos.map(apontamento => {
                        const s = STATUS_LABEL[apontamento.status] ?? { label: apontamento.status, color: 'text-slate-400' }
                        const termino = apontamento.producao_fim ?? apontamento.setup_fim
                        return (
                          <tr
                            key={apontamento.id}
                            onClick={() => setSelecionado(apontamento)}
                            className="hover:bg-white/[0.04] transition-colors cursor-pointer"
                          >
                            <td className="px-4 py-2 text-white">{apontamento.operario ?? '—'}</td>
                            <td className="px-4 py-2">
                              <span className="font-mono text-white">{apontamento.ordem_lote}</span>
                              {apontamento.desc_peca && (
                                <span className="block text-slate-500 truncate max-w-[160px]">{apontamento.desc_peca}</span>
                              )}
                            </td>
                            <td className="px-4 py-2 text-slate-300">{fmtDataHora(apontamento.setup_inicio)}</td>
                            <td className="px-4 py-2 text-slate-300">{fmtDataHora(termino)}</td>
                            <td className="px-4 py-2 text-right text-slate-300">{apontamento.qtd_pecas}</td>
                            <td className="px-4 py-2 text-right text-slate-300">{apontamento.qtd_pilhas}</td>
                            <td className="px-4 py-2 text-right text-slate-300">{fmtDuracao(apontamento.tempo_setup_segundos)}</td>
                            <td className="px-4 py-2 text-right text-slate-300">{fmtDuracao(apontamento.tempo_producao_segundos)}</td>
                            <td className={`px-4 py-2 font-medium ${s.color}`}>{s.label}</td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
              </>
            )}
          </div>
        </div>
      </div>

      <ApontamentoDetalheModal resumo={selecionado} onClose={() => setSelecionado(null)} />
    </>
  )
}
