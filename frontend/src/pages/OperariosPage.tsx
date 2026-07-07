import { useCallback, useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { Users, Loader2, Plus, Pencil, Barcode } from 'lucide-react'
import { getOperarios, type Operario } from '@/api/operarios'
import { OperarioFormModal } from '@/components/OperarioFormModal'
import { CrachaOperarioModal } from '@/components/CrachaOperarioModal'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

type Filtro = 'todos' | 'ativos' | 'inativos'

const FILTROS: { value: Filtro; label: string }[] = [
  { value: 'todos',    label: 'Todos'    },
  { value: 'ativos',   label: 'Ativos'   },
  { value: 'inativos', label: 'Inativos' },
]

const HEADER_CLASS = 'px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider'

const operarioColumns: ResponsiveTableColumn<Operario>[] = [
  {
    key: 'nome',
    header: 'Nome',
    render: (o) => o.user.name,
    headerClassName: HEADER_CLASS,
    cellClassName: 'px-6 py-4 font-medium text-white',
  },
  {
    key: 'matricula',
    header: 'Matrícula',
    render: (o) => o.matricula,
    headerClassName: HEADER_CLASS,
    cellClassName: 'px-6 py-4 text-slate-300',
  },
  {
    key: 'setor',
    header: 'Setor',
    render: (o) =>
      o.etapa_fluxo
        ? <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-[#00aa84]/10 text-[#00aa84]">{o.etapa_fluxo.nome}</span>
        : <span className="text-slate-600">—</span>,
    headerClassName: HEADER_CLASS,
    cellClassName: 'px-6 py-4',
  },
  {
    key: 'status',
    header: 'Status',
    render: (o) =>
      o.user.ativo
        ? <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-500/10 text-emerald-400">Ativo</span>
        : <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-red-500/10 text-red-400">Inativo</span>,
    headerClassName: HEADER_CLASS,
    cellClassName: 'px-6 py-4',
  },
  {
    key: 'email',
    header: 'E-mail',
    render: (o) => o.user.email,
    headerClassName: HEADER_CLASS,
    cellClassName: 'px-6 py-4 text-slate-400',
  },
]

export function OperariosPage() {
  const [operarios, setOperarios]   = useState<Operario[]>([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState<string | null>(null)
  const [filtro, setFiltro]         = useState<Filtro>('ativos')
  const [modalOpen, setModalOpen]   = useState(false)
  const [editTarget, setEditTarget] = useState<Operario | undefined>(undefined)
  const [crachaTarget, setCrachaTarget] = useState<Operario | null>(null)

  const load = useCallback((signal?: AbortSignal) => {
    setLoading(true)
    setError(null)

    getOperarios(signal)
      .then(setOperarios)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) {
          setError('Não foi possível carregar os operários.')
        }
      })
      .finally(() => {
        if (!signal?.aborted) setLoading(false)
      })
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    load(controller.signal)
    return () => controller.abort()
  }, [load])

  const filtered = useMemo(() => {
    if (filtro === 'ativos')   return operarios.filter(o => o.user.ativo)
    if (filtro === 'inativos') return operarios.filter(o => !o.user.ativo)
    return operarios
  }, [operarios, filtro])

  function openCreate() {
    setEditTarget(undefined)
    setModalOpen(true)
  }

  function openEdit(o: Operario) {
    setEditTarget(o)
    setModalOpen(true)
  }

  function handleClose() {
    setModalOpen(false)
    setEditTarget(undefined)
  }

  return (
    <>
      <div className="space-y-6 print:hidden">
        {/* cabeçalho */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-[#00aa84]/10">
              <Users className="w-5 h-5 text-[#00aa84]" />
            </div>
            <div>
              <h1 className="text-xl font-semibold text-white">Operários</h1>
              <p className="text-sm text-slate-400">Gerencie os operários cadastrados</p>
            </div>
          </div>

          <button
            onClick={openCreate}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 rounded-lg transition-colors"
          >
            <Plus className="w-4 h-4" />
            Cadastrar
          </button>
        </div>

        {/* filtros */}
        <div className="flex items-center gap-1 p-1 bg-white/5 rounded-lg w-fit">
          {FILTROS.map(f => (
            <button
              key={f.value}
              onClick={() => setFiltro(f.value)}
              className={`px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
                filtro === f.value
                  ? 'bg-[#00aa84] text-white'
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              {f.label}
            </button>
          ))}
        </div>

        {/* tabela */}
        <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
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
          {!loading && !error && filtered.length === 0 && (
            <div className="flex items-center justify-center py-16">
              <p className="text-sm text-slate-500">
                {filtro === 'todos'
                  ? 'Nenhum operário cadastrado.'
                  : filtro === 'ativos'
                    ? 'Nenhum operário ativo.'
                    : 'Nenhum operário inativo.'}
              </p>
            </div>
          )}
          {!loading && !error && filtered.length > 0 && (
            <ResponsiveTable
              columns={operarioColumns}
              data={filtered}
              keyExtractor={(o) => o.id}
              renderActions={(o) => (
                <>
                  <button
                    onClick={() => setCrachaTarget(o)}
                    className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                    title="Imprimir crachá"
                  >
                    <Barcode className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => openEdit(o)}
                    className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                    title="Editar operário"
                  >
                    <Pencil className="w-4 h-4" />
                  </button>
                </>
              )}
            />
          )}
        </div>
      </div>

      <OperarioFormModal
        open={modalOpen}
        onClose={handleClose}
        onSuccess={() => load()}
        initialData={editTarget}
      />

      <CrachaOperarioModal
        operario={crachaTarget}
        onClose={() => setCrachaTarget(null)}
      />
    </>
  )
}
