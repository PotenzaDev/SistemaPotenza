import { useEffect, useState } from 'react'
import axios from 'axios'
import { Clock, Loader2, Printer } from 'lucide-react'
import {
  getTimelineMaquinas,
  getFiltrosRelatorioMaquinas,
  type TimelineMaquinasResponse,
  type TimelineMaquinasFiltros,
  type FiltrosRelatorioMaquinas,
  type TimelineMaquina,
  type TimelineTipoSegmento,
} from '@/api/relatorios'
import { ApontamentosMaquinaModal } from '@/components/ApontamentosMaquinaModal'
import { MaquinaTimelineBar } from '@/components/MaquinaTimelineBar'

const INPUT_CLASS =
  'w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white ' +
  'placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors'

const PRESET_BUTTON_CLASS =
  'px-3 py-1.5 text-xs font-medium rounded-lg border border-white/10 text-slate-300 ' +
  'hover:border-[#00aa84]/60 hover:text-white transition-colors'

const POLL_INTERVAL_MS = 15_000

const LEGENDA: { tipo: TimelineTipoSegmento; label: string; cor: string }[] = [
  { tipo: 'producao', label: 'Produção', cor: '#00aa84' },
  { tipo: 'setup',    label: 'Setup',    cor: '#3b82f6' },
  { tipo: 'pausa',    label: 'Pausa',    cor: '#f97316' },
  { tipo: 'parado',   label: 'Parado',   cor: '#ef4444' },
]

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

export function RelatorioTimelineMaquinasPage() {
  const [filtros, setFiltros] = useState<TimelineMaquinasFiltros>(() => ({ data: hoje() }))
  const [opcoes, setOpcoes]   = useState<FiltrosRelatorioMaquinas>({ grupos: [], maquinas: [] })
  const [dados, setDados]     = useState<TimelineMaquinasResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState<string | null>(null)
  const [maquinaSelecionada, setMaquinaSelecionada] = useState<TimelineMaquina | null>(null)

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
    let cancelado = false
    const controller = new AbortController()

    function carregar(mostrarLoading: boolean) {
      if (mostrarLoading) setLoading(true)
      setError(null)

      getTimelineMaquinas(filtros, controller.signal)
        .then(res => {
          if (!cancelado) setDados(res)
        })
        .catch((err: unknown) => {
          if (!axios.isCancel(err) && !cancelado) setError('Não foi possível carregar a linha do tempo.')
        })
        .finally(() => {
          if (!cancelado && mostrarLoading) setLoading(false)
        })
    }

    carregar(true)

    // Só atualiza automaticamente quando a data selecionada é hoje — a
    // timeline vai se preenchendo ao longo do dia sem recarregar a página.
    const intervalo = filtros.data === hoje()
      ? window.setInterval(() => carregar(false), POLL_INTERVAL_MS)
      : undefined

    return () => {
      cancelado = true
      controller.abort()
      if (intervalo) window.clearInterval(intervalo)
    }
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
  const turno    = dados?.turno ?? null
  const isHoje   = filtros.data === hoje()

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <Clock className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Linha do Tempo de Máquinas</h1>
            <p className="text-sm text-slate-400">Setup, produção, pausa e parado ao longo do turno</p>
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
        <p className="text-sm text-slate-300">Data: {filtros.data}</p>
      </div>

      <div className="print:hidden bg-[#0f1923] border border-white/5 rounded-xl p-4 space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">Data</label>
            <input
              type="date"
              value={filtros.data}
              max={hoje()}
              onChange={e => setFiltros(f => ({ ...f, data: e.target.value }))}
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
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex flex-wrap gap-2">
            <button type="button" className={PRESET_BUTTON_CLASS} onClick={() => setFiltros(f => ({ ...f, data: hoje() }))}>
              Hoje
            </button>
            <button type="button" className={PRESET_BUTTON_CLASS} onClick={() => setFiltros(f => ({ ...f, data: diasAtras(1) }))}>
              Ontem
            </button>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            {LEGENDA.map(item => (
              <div key={item.tipo} className="flex items-center gap-1.5 text-xs text-slate-400">
                <span className="w-2.5 h-2.5 rounded-sm" style={{ backgroundColor: item.cor }} />
                {item.label}
              </div>
            ))}
          </div>
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
      {!loading && !error && !turno && (
        <div className="flex items-center justify-center py-16">
          <p className="text-sm text-slate-500">Nenhum turno configurado para este dia.</p>
        </div>
      )}
      {!loading && !error && turno && maquinas.length === 0 && (
        <div className="flex items-center justify-center py-16">
          <p className="text-sm text-slate-500">Nenhuma máquina encontrada para os filtros selecionados.</p>
        </div>
      )}

      {!loading && !error && turno && maquinas.length > 0 && (
        <div className="bg-[#0f1923] border border-white/5 rounded-xl p-4 space-y-4">
          {maquinas.map(maquina => (
            <div
              key={maquina.maquina_id}
              onClick={() => setMaquinaSelecionada(maquina)}
              className="cursor-pointer hover:bg-white/[0.02] rounded-lg p-2 -m-2 transition-colors"
            >
              <div className="flex items-center justify-between mb-1.5">
                <p className="text-sm font-medium text-white">{maquina.maquina}</p>
                <p className="text-xs text-slate-500">{maquina.grupo?.nome ?? '—'}</p>
              </div>
              <MaquinaTimelineBar turno={turno} segmentos={maquina.segmentos} isHoje={isHoje} />
            </div>
          ))}
        </div>
      )}

      <ApontamentosMaquinaModal
        maquina={maquinaSelecionada}
        filtros={{ dataInicio: filtros.data, dataFim: filtros.data, grupoId: filtros.grupoId, maquinaId: filtros.maquinaId }}
        onClose={() => setMaquinaSelecionada(null)}
      />
    </div>
  )
}
