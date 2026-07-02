import { useEffect, useState } from 'react'
import axios from 'axios'
import { Cpu, Loader2, Printer } from 'lucide-react'
import {
  getRelatorioProducaoMaquinas,
  getFiltrosRelatorioMaquinas,
  type RelatorioMaquinasResponse,
  type RelatorioMaquinasFiltros,
  type FiltrosRelatorioMaquinas,
  type RelatorioMaquina,
} from '@/api/relatorios'
import { ApontamentosMaquinaModal } from '@/components/ApontamentosMaquinaModal'
import { fmtDuracao } from '@/lib/apontamentoFormat'
import {
  PieChart, Pie, Cell, Legend, Tooltip, ResponsiveContainer,
  BarChart, Bar, XAxis, YAxis, CartesianGrid,
} from 'recharts'
import type { TooltipValueType } from 'recharts'

const INPUT_CLASS =
  'w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white ' +
  'placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors'

const PRESET_BUTTON_CLASS =
  'px-3 py-1.5 text-xs font-medium rounded-lg border border-white/10 text-slate-300 ' +
  'hover:border-[#00aa84]/60 hover:text-white transition-colors'

const TOOLTIP_STYLE = { background: '#0f1923', border: '1px solid rgba(255,255,255,0.1)', borderRadius: 8 }
const TOOLTIP_LABEL_STYLE = { color: '#94a3b8' }
const LEGEND_STYLE = { fontSize: 12, color: '#94a3b8' }

const PIE_COLORS = {
  Produção: '#00aa84',
  Setup:    '#3b82f6',
  Parado:   '#ef4444',
}

const BARRA_COLORS = {
  Produção: '#00aa84',
  Setup:    '#3b82f6',
  Parado:   '#ef4444',
}

function toIsoDate(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function hoje(): string {
  return toIsoDate(new Date())
}

function diasAtras(dias: number): string {
  const data = new Date()
  data.setDate(data.getDate() - dias)
  return toIsoDate(data)
}

function inicioDoMes(): string {
  const data = new Date()
  data.setDate(1)
  return toIsoDate(data)
}

function formatTempoTooltip(value: TooltipValueType | undefined): string {
  return typeof value === 'number' ? fmtDuracao(value) : '—'
}

export function RelatorioProducaoMaquinasPage() {
  const [filtros, setFiltros] = useState<RelatorioMaquinasFiltros>(() => ({ dataInicio: hoje(), dataFim: hoje() }))
  const [opcoes, setOpcoes]   = useState<FiltrosRelatorioMaquinas>({ grupos: [], maquinas: [] })
  const [dados, setDados]     = useState<RelatorioMaquinasResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState<string | null>(null)
  const [maquinaSelecionada, setMaquinaSelecionada] = useState<RelatorioMaquina | null>(null)

  useEffect(() => {
    const controller = new AbortController()

    getFiltrosRelatorioMaquinas(controller.signal)
      .then(setOpcoes)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setOpcoes({ grupos: [], maquinas: [] })
      })

    return () => controller.abort()
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    setLoading(true)
    setError(null)

    getRelatorioProducaoMaquinas(filtros, controller.signal)
      .then(setDados)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) {
          setError('Não foi possível carregar o relatório.')
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })

    return () => controller.abort()
  }, [filtros])

  function handleGrupoChange(value: string) {
    const grupoId = value ? Number(value) : undefined
    setFiltros(f => ({ ...f, grupoId, maquinaId: undefined }))
  }

  function handleMaquinaChange(value: string) {
    setFiltros(f => ({ ...f, maquinaId: value ? Number(value) : undefined }))
  }

  const maquinasDoFiltro = opcoes.maquinas.filter(
    m => !filtros.grupoId || m.etapa_fluxo_id === filtros.grupoId,
  )

  const maquinas = dados?.maquinas ?? []
  const totais   = dados?.totais

  const dadosPizza = totais
    ? [
        { name: 'Produção', value: totais.tempo_producao_segundos },
        { name: 'Setup',    value: totais.tempo_setup_segundos },
        { name: 'Parado',   value: totais.tempo_parado_segundos },
      ].filter(item => item.value > 0)
    : []

  const dadosBarra = maquinas.map(m => ({
    nome:     m.maquina,
    Produção: m.tempo_producao_segundos,
    Setup:    m.tempo_setup_segundos,
    Parado:   m.tempo_parado_segundos,
  }))

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <Cpu className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Produção de Máquinas</h1>
            <p className="text-sm text-slate-400">Utilização, setup, parada e peças produzidas por máquina</p>
          </div>
        </div>
        <button
          type="button"
          onClick={() => window.print()}
          className="print:hidden flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#00aa84]/10 text-[#00aa84] border border-[#00aa84]/30 hover:bg-[#00aa84]/20 transition-colors"
        >
          <Printer className="w-4 h-4" />
          Gerar PDF
        </button>
      </div>

      <div className="hidden print:block">
        <p className="text-sm text-slate-300">
          Período: {filtros.dataInicio} a {filtros.dataFim}
        </p>
      </div>

      <div className="print:hidden bg-[#0f1923] border border-white/5 rounded-xl p-4 space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">De</label>
            <input
              type="date"
              value={filtros.dataInicio}
              max={filtros.dataFim}
              onChange={e => setFiltros({ ...filtros, dataInicio: e.target.value })}
              className={INPUT_CLASS}
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">Até</label>
            <input
              type="date"
              value={filtros.dataFim}
              min={filtros.dataInicio}
              max={hoje()}
              onChange={e => setFiltros({ ...filtros, dataFim: e.target.value })}
              className={INPUT_CLASS}
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">Setor</label>
            <select
              value={filtros.grupoId ?? ''}
              onChange={e => handleGrupoChange(e.target.value)}
              className={INPUT_CLASS}
            >
              <option value="">Todos os setores</option>
              {opcoes.grupos.map(grupo => (
                <option key={grupo.id} value={grupo.id}>{grupo.nome}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">Máquina</label>
            <select
              value={filtros.maquinaId ?? ''}
              onChange={e => handleMaquinaChange(e.target.value)}
              className={INPUT_CLASS}
            >
              <option value="">Todas as máquinas</option>
              {maquinasDoFiltro.map(maquina => (
                <option key={maquina.id} value={maquina.id}>{maquina.nome}</option>
              ))}
            </select>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <button type="button" className={PRESET_BUTTON_CLASS} onClick={() => setFiltros(f => ({ ...f, dataInicio: hoje(), dataFim: hoje() }))}>
            Hoje
          </button>
          <button type="button" className={PRESET_BUTTON_CLASS} onClick={() => setFiltros(f => ({ ...f, dataInicio: diasAtras(6), dataFim: hoje() }))}>
            Última Semana
          </button>
          <button type="button" className={PRESET_BUTTON_CLASS} onClick={() => setFiltros(f => ({ ...f, dataInicio: inicioDoMes(), dataFim: hoje() }))}>
            Este Mês
          </button>
          <button type="button" className={PRESET_BUTTON_CLASS} onClick={() => setFiltros(f => ({ ...f, dataInicio: diasAtras(365), dataFim: hoje() }))}>
            Último Ano
          </button>
        </div>
      </div>

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
      {!loading && !error && maquinas.length === 0 && (
        <div className="flex items-center justify-center py-16">
          <p className="text-sm text-slate-500">Nenhuma máquina encontrada para o período selecionado.</p>
        </div>
      )}

      {!loading && !error && maquinas.length > 0 && dados && totais && (
        <>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 print:break-inside-avoid">
            <div className="bg-[#0f1923] border border-white/5 rounded-xl p-4">
              <h2 className="text-sm font-semibold text-white mb-2">Distribuição do Tempo Total</h2>
              <ResponsiveContainer width="100%" height={220}>
                <PieChart>
                  <Pie data={dadosPizza} dataKey="value" nameKey="name" innerRadius={50} outerRadius={80} paddingAngle={2}>
                    {dadosPizza.map(item => (
                      <Cell key={item.name} fill={PIE_COLORS[item.name as keyof typeof PIE_COLORS]} />
                    ))}
                  </Pie>
                  <Tooltip contentStyle={TOOLTIP_STYLE} labelStyle={TOOLTIP_LABEL_STYLE} formatter={formatTempoTooltip} />
                  <Legend wrapperStyle={LEGEND_STYLE} />
                </PieChart>
              </ResponsiveContainer>
            </div>

            <div className="bg-[#0f1923] border border-white/5 rounded-xl p-4">
              <h2 className="text-sm font-semibold text-white mb-2">Tempo por Máquina</h2>
              <ResponsiveContainer width="100%" height={220}>
                <BarChart data={dadosBarra}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" />
                  <XAxis dataKey="nome" tick={{ fontSize: 10, fill: '#94a3b8' }} interval={0} angle={-20} textAnchor="end" height={60} />
                  <YAxis tick={{ fontSize: 10, fill: '#94a3b8' }} tickFormatter={v => String(Math.round(v / 3600))} label={{ value: 'horas', angle: -90, position: 'insideLeft', fill: '#94a3b8', fontSize: 11 }} />
                  <Tooltip contentStyle={TOOLTIP_STYLE} labelStyle={TOOLTIP_LABEL_STYLE} formatter={formatTempoTooltip} />
                  <Legend wrapperStyle={LEGEND_STYLE} />
                  <Bar dataKey="Produção" stackId="tempo" fill={BARRA_COLORS.Produção} />
                  <Bar dataKey="Setup" stackId="tempo" fill={BARRA_COLORS.Setup} />
                  <Bar dataKey="Parado" stackId="tempo" fill={BARRA_COLORS.Parado} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>

          <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left">
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Máquina</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Grupo</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">Tempo Setup</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">Tempo Produção</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">Tempo Parado</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">% Utilização</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider text-right">Peças Produzidas</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {maquinas.map(maquina => (
                  <tr
                    key={maquina.maquina_id}
                    onClick={() => setMaquinaSelecionada(maquina)}
                    className="hover:bg-white/[0.02] transition-colors cursor-pointer"
                  >
                    <td className="px-6 py-3 text-white">{maquina.maquina}</td>
                    <td className="px-6 py-3 text-slate-300">{maquina.grupo?.nome ?? '—'}</td>
                    <td className="px-6 py-3 text-right text-slate-300">{fmtDuracao(maquina.tempo_setup_segundos)}</td>
                    <td className="px-6 py-3 text-right text-slate-300">{fmtDuracao(maquina.tempo_producao_segundos)}</td>
                    <td className="px-6 py-3 text-right text-slate-300">{fmtDuracao(maquina.tempo_parado_segundos)}</td>
                    <td className="px-6 py-3 text-right text-slate-300">{maquina.percentual_utilizacao.toFixed(1)}%</td>
                    <td className="px-6 py-3 text-right text-slate-300">{maquina.qtd_pecas}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t border-white/10 bg-white/[0.02]">
                  <td colSpan={2} className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">
                    Total ({dados.dias_considerados} {dados.dias_considerados === 1 ? 'dia' : 'dias'} com turno)
                  </td>
                  <td className="px-6 py-3 text-right font-semibold text-white">{fmtDuracao(totais.tempo_setup_segundos)}</td>
                  <td className="px-6 py-3 text-right font-semibold text-white">{fmtDuracao(totais.tempo_producao_segundos)}</td>
                  <td className="px-6 py-3 text-right font-semibold text-white">{fmtDuracao(totais.tempo_parado_segundos)}</td>
                  <td className="px-6 py-3 text-right font-semibold text-white">—</td>
                  <td className="px-6 py-3 text-right font-semibold text-white">{totais.qtd_pecas}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </>
      )}

      <ApontamentosMaquinaModal
        maquina={maquinaSelecionada}
        filtros={filtros}
        onClose={() => setMaquinaSelecionada(null)}
      />
    </div>
  )
}
