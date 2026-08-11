import { useEffect, useState } from 'react'
import axios from 'axios'
import { ClipboardList, Download, Loader2 } from 'lucide-react'
import {
  getRelatorioApontamentos,
  getFiltrosRelatorioApontamentos,
  baixarRelatorioApontamentosXlsx,
  type RelatorioApontamentosFiltros,
  type FiltrosRelatorioApontamentos,
  type LinhaRelatorioApontamento,
  type TipoSegmentoApontamento,
} from '@/api/relatorios'
import { baixarArquivo } from '@/lib/download'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

const INPUT_CLASS =
  'w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white ' +
  'placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors'

const PRESET_BUTTON_CLASS =
  'px-3 py-1.5 text-xs font-medium rounded-lg border border-white/10 text-slate-300 ' +
  'hover:border-[#00aa84]/60 hover:text-white transition-colors'

const HEADER_CLASS = 'px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider'

const TIPO_LABEL: Record<TipoSegmentoApontamento, { label: string; color: string }> = {
  setup:    { label: 'Setup',    color: 'text-blue-400' },
  producao: { label: 'Produção', color: 'text-[#00aa84]' },
  pausa:    { label: 'Pausa',    color: 'text-orange-400' },
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

function fmtDataBR(data: string): string {
  const [ano, mes, dia] = data.split('-')
  return `${dia}/${mes}/${ano}`
}

function fmtHoraLocal(dataHora: string): string {
  return dataHora.slice(11, 16)
}

export function RelatorioApontamentosPage() {
  const [filtros, setFiltros] = useState<RelatorioApontamentosFiltros>(() => ({ dataInicio: hoje(), dataFim: hoje() }))
  const [opcoes, setOpcoes]   = useState<FiltrosRelatorioApontamentos>({ grupos: [], maquinas: [], operarios: [] })
  const [linhas, setLinhas]   = useState<LinhaRelatorioApontamento[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState<string | null>(null)
  const [baixando, setBaixando] = useState(false)

  useEffect(() => {
    const controller = new AbortController()

    getFiltrosRelatorioApontamentos(controller.signal)
      .then(setOpcoes)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setOpcoes({ grupos: [], maquinas: [], operarios: [] })
      })

    return () => controller.abort()
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    setLoading(true)
    setError(null)

    getRelatorioApontamentos(filtros, controller.signal)
      .then(setLinhas)
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

  async function handleBaixarExcel() {
    setBaixando(true)
    try {
      const blob = await baixarRelatorioApontamentosXlsx(filtros)
      baixarArquivo(blob, `relatorio-apontamentos-${filtros.dataInicio}-a-${filtros.dataFim}.xlsx`)
    } catch {
      setError('Não foi possível gerar a planilha.')
    } finally {
      setBaixando(false)
    }
  }

  const maquinasDoFiltro = opcoes.maquinas.filter(
    m => !filtros.grupoId || m.etapa_fluxo_id === filtros.grupoId,
  )

  const colunas: ResponsiveTableColumn<LinhaRelatorioApontamento>[] = [
    {
      key: 'data',
      header: 'Data',
      render: (l) => fmtDataBR(l.data),
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-white',
    },
    {
      key: 'maquina',
      header: 'Máquina',
      render: (l) => `${l.maquina} (#${l.maquina_id})`,
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300',
    },
    {
      key: 'setor',
      header: 'Setor',
      render: (l) => l.setor ?? '—',
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300',
    },
    {
      key: 'usuario',
      header: 'Usuário',
      render: (l) => `${l.usuario} (#${l.user_id ?? '—'})`,
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300',
    },
    {
      key: 'lote',
      header: 'Lote',
      render: (l) => l.lote,
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300',
    },
    {
      key: 'qtd_total_produzida',
      header: 'Qtd Produzida',
      render: (l) => l.qtd_total_produzida,
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300',
    },
    {
      key: 'fichas',
      header: 'Fichas',
      render: (l) => l.fichas || '—',
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300 max-w-xs truncate',
    },
    {
      key: 'tipo',
      header: 'Tipo',
      render: (l) => <span className={TIPO_LABEL[l.tipo].color}>{TIPO_LABEL[l.tipo].label}</span>,
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3',
    },
    {
      key: 'motivo_pausa',
      header: 'Motivo Pausa',
      render: (l) => l.motivo_pausa ?? '—',
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300',
    },
    {
      key: 'inicio',
      header: 'Início',
      render: (l) => fmtHoraLocal(l.inicio),
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300',
    },
    {
      key: 'fim',
      header: 'Fim',
      render: (l) => fmtHoraLocal(l.fim),
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300',
    },
    {
      key: 'duracao_segundos',
      header: 'Duração',
      render: (l) => {
        const h = Math.floor(l.duracao_segundos / 3600)
        const m = Math.floor((l.duracao_segundos % 3600) / 60)
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
      },
      headerClassName: HEADER_CLASS,
      cellClassName: 'px-4 py-3 text-slate-300',
    },
  ]

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <ClipboardList className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Apontamentos</h1>
            <p className="text-sm text-slate-400">Setup, produção e pausas de cada apontamento, em ordem cronológica</p>
          </div>
        </div>
        <button
          type="button"
          onClick={handleBaixarExcel}
          disabled={baixando || linhas.length === 0}
          className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#00aa84]/10 text-[#00aa84] border border-[#00aa84]/30 hover:bg-[#00aa84]/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {baixando ? <Loader2 className="w-4 h-4 animate-spin" /> : <Download className="w-4 h-4" />}
          Baixar Excel
        </button>
      </div>

      <div className="bg-[#0f1923] border border-white/5 rounded-xl p-4 space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
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
            <select value={filtros.grupoId ?? ''} onChange={e => handleGrupoChange(e.target.value)} className={INPUT_CLASS}>
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
              onChange={e => setFiltros(f => ({ ...f, maquinaId: e.target.value ? Number(e.target.value) : undefined }))}
              className={INPUT_CLASS}
            >
              <option value="">Todas as máquinas</option>
              {maquinasDoFiltro.map(maquina => (
                <option key={maquina.id} value={maquina.id}>{maquina.nome}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">Operário</label>
            <select
              value={filtros.operarioId ?? ''}
              onChange={e => setFiltros(f => ({ ...f, operarioId: e.target.value ? Number(e.target.value) : undefined }))}
              className={INPUT_CLASS}
            >
              <option value="">Todos os operários</option>
              {opcoes.operarios.map(operario => (
                <option key={operario.id} value={operario.id}>{operario.nome}</option>
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
      {!loading && !error && linhas.length === 0 && (
        <div className="flex items-center justify-center py-16">
          <p className="text-sm text-slate-500">Nenhum apontamento encontrado para o período selecionado.</p>
        </div>
      )}

      {!loading && !error && linhas.length > 0 && (
        <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
          <ResponsiveTable
            columns={colunas}
            data={linhas}
            keyExtractor={(l) => `${l.maquina_id}-${l.operario_id}-${l.inicio}-${l.tipo}`}
          />
        </div>
      )}
    </div>
  )
}
