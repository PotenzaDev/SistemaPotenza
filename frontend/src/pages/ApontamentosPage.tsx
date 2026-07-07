import { useEffect, useState, useMemo } from 'react'
import axios from 'axios'
import { ClipboardList, Loader2 } from 'lucide-react'
import { getApontamentosDoDia, type ApontamentoDoDia, type ApontamentosDoDia, type ApontamentoFiltros } from '@/api/apontamentos'
import { ApontamentosFiltro } from '@/components/ApontamentosFiltro'
import { ApontamentoDetalheModal } from '@/components/ApontamentoDetalheModal'
import { STATUS_LABEL, fmtDuracao, fmtHora } from '@/lib/apontamentoFormat'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

function hoje(): string {
  return new Date().toISOString().slice(0, 10)
}

const APONTAMENTO_HEADER_CLASS = 'px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider'
const APONTAMENTO_HEADER_CLASS_RIGHT = `${APONTAMENTO_HEADER_CLASS} text-right`

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

  const apontamentoColumns = useMemo<ResponsiveTableColumn<ApontamentoDoDia>[]>(() => [
    {
      key: 'operario',
      header: 'Operário',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="cursor-pointer">{a.operario ?? '—'}</div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS,
      cellClassName: 'px-6 py-3 text-white',
    },
    {
      key: 'maquina',
      header: 'Máquina',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="cursor-pointer">{a.maquina ?? '—'}</div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS,
      cellClassName: 'px-6 py-3 text-slate-300',
    },
    {
      key: 'ordem_lote',
      header: 'Ordem / Lote',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="group relative inline-block cursor-pointer">
          <span className="font-mono text-white">{a.ordem_lote}</span>
          <span className="block text-xs text-slate-500">{a.cod_peca.slice(0, -2)}</span>
          {a.desc_peca && (
            <div className="pointer-events-none absolute bottom-full left-0 mb-1 hidden group-hover:block bg-slate-700 border border-white/10 text-white text-xs rounded-lg px-3 py-1.5 whitespace-nowrap z-20 shadow-xl">
              {a.desc_peca}
            </div>
          )}
        </div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS,
      cellClassName: 'px-6 py-3',
    },
    {
      key: 'setup_inicio',
      header: 'Início',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="cursor-pointer">{fmtHora(a.setup_inicio)}</div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS,
      cellClassName: 'px-6 py-3 text-slate-300',
    },
    {
      key: 'termino',
      header: 'Término',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="cursor-pointer">{fmtHora(a.producao_fim ?? a.setup_fim)}</div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS,
      cellClassName: 'px-6 py-3 text-slate-300',
    },
    {
      key: 'qtd_pecas',
      header: 'Peças',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="cursor-pointer">{a.qtd_pecas}</div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS_RIGHT,
      cellClassName: 'px-6 py-3 text-right text-slate-300',
    },
    {
      key: 'qtd_pilhas',
      header: 'Pilhas',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="cursor-pointer">{a.qtd_pilhas}</div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS_RIGHT,
      cellClassName: 'px-6 py-3 text-right text-slate-300',
    },
    {
      key: 'tempo_setup_segundos',
      header: 'Tempo Setup',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="cursor-pointer">{fmtDuracao(a.tempo_setup_segundos)}</div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS_RIGHT,
      cellClassName: 'px-6 py-3 text-right text-slate-300',
    },
    {
      key: 'tempo_producao_segundos',
      header: 'Tempo Produção',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="cursor-pointer">{fmtDuracao(a.tempo_producao_segundos)}</div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS_RIGHT,
      cellClassName: 'px-6 py-3 text-right text-slate-300',
    },
    {
      key: 'numero_pausas',
      header: 'Pausas',
      render: (a) => (
        <div onClick={() => setSelecionado(a)} className="cursor-pointer">{a.numero_pausas}</div>
      ),
      headerClassName: APONTAMENTO_HEADER_CLASS_RIGHT,
      cellClassName: 'px-6 py-3 text-right text-slate-300',
    },
    {
      key: 'status',
      header: 'Status',
      render: (a) => {
        const s = STATUS_LABEL[a.status] ?? { label: a.status, color: 'text-slate-400' }
        return (
          <div onClick={() => setSelecionado(a)} className={`cursor-pointer ${s.color}`}>{s.label}</div>
        )
      },
      headerClassName: APONTAMENTO_HEADER_CLASS,
      cellClassName: 'px-6 py-3 font-medium',
    },
  ], [])

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
          <ResponsiveTable
            columns={apontamentoColumns}
            data={apontamentos}
            keyExtractor={(a) => a.id}
          />

          {/* Total do período — desktop */}
          <div className="hidden md:block">
            <table className="w-full text-sm">
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

          {/* Total do período — mobile */}
          <div className="md:hidden px-4 py-3 border-t border-white/10 bg-white/[0.02] space-y-1.5">
            <div className="text-xs font-medium text-slate-400 uppercase tracking-wider">Total do período</div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-xs text-slate-500">Peças</span>
              <span className="font-semibold text-white">{dados.totais.qtd_pecas}</span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-xs text-slate-500">Pilhas</span>
              <span className="font-semibold text-white">{dados.totais.qtd_pilhas}</span>
            </div>
          </div>
        </div>
      )}

      <ApontamentoDetalheModal resumo={selecionado} onClose={() => setSelecionado(null)} />
    </div>
  )
}
