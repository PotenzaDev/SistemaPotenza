import { useCallback, useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { Boxes, CheckCircle2, XCircle, Loader2, Plus, Pencil } from 'lucide-react'
import { getBrocas, type Broca } from '@/api/brocas'
import { BrocaFormModal } from '@/components/BrocaFormModal'
import { useAuth } from '@/hooks/useAuth'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

type Filtro = 'todos' | 'ativos' | 'inativos'

const FILTROS: { value: Filtro; label: string }[] = [
  { value: 'todos',    label: 'Todos'    },
  { value: 'ativos',   label: 'Ativos'   },
  { value: 'inativos', label: 'Inativos' },
]

const brocaColumns: ResponsiveTableColumn<Broca>[] = [
  {
    key: 'codigo',
    header: 'Código',
    render: (b) => b.codigo,
    cellClassName: 'px-4 py-3 font-medium text-white font-mono text-xs',
  },
  {
    key: 'espessura',
    header: 'Espessura / Diâmetro',
    render: (b) => `${b.espessura_mm} mm`,
  },
  {
    key: 'rotacao',
    header: 'Rotação',
    render: (b) => b.rotacao,
    cellClassName: 'px-4 py-3 text-slate-300 capitalize',
  },
  { key: 'altura', header: 'Altura', render: (b) => `${b.altura_mm} mm` },
  { key: 'furo_passante', header: 'Furo Passante', render: (b) => (b.furo_passante ? 'Sim' : 'Não') },
  {
    key: 'status',
    header: 'Status',
    render: (b) =>
      b.ativo ? (
        <span className="inline-flex items-center gap-1.5 text-[#00aa84]">
          <CheckCircle2 className="w-4 h-4" /> Ativa
        </span>
      ) : (
        <span className="inline-flex items-center gap-1.5 text-slate-500">
          <XCircle className="w-4 h-4" /> Inativa
        </span>
      ),
    cellClassName: 'px-4 py-3',
  },
]

export function BrocasPage() {
  const { user }                     = useAuth()
  const [brocas, setBrocas]         = useState<Broca[]>([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState<string | null>(null)
  const [filtro, setFiltro]         = useState<Filtro>('todos')
  const [modalOpen, setModalOpen]   = useState(false)
  const [editingBroca, setEditingBroca] = useState<Broca | undefined>()

  const canCreate = user?.role === 'admin' || user?.role === 'funcionario'

  const load = useCallback((signal?: AbortSignal) => {
    setLoading(true)
    setError(null)
    getBrocas(signal)
      .then(setBrocas)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar as brocas.')
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
    if (filtro === 'ativos')   return brocas.filter(b => b.ativo)
    if (filtro === 'inativos') return brocas.filter(b => !b.ativo)
    return brocas
  }, [brocas, filtro])

  function openCreate() {
    setEditingBroca(undefined)
    setModalOpen(true)
  }

  function openEdit(b: Broca) {
    setEditingBroca(b)
    setModalOpen(true)
  }

  function handleClose() {
    setModalOpen(false)
    setEditingBroca(undefined)
  }

  return (
    <div className="space-y-6">

      {/* cabeçalho */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <Boxes className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Brocas</h1>
            <p className="text-sm text-slate-400">Gerencie o cadastro de brocas</p>
          </div>
        </div>

        {canCreate && (
          <button
            onClick={openCreate}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 rounded-lg transition-colors"
          >
            <Plus className="w-4 h-4" />
            Cadastrar
          </button>
        )}
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
                ? 'Nenhuma broca cadastrada.'
                : filtro === 'ativos'
                  ? 'Nenhuma broca ativa.'
                  : 'Nenhuma broca inativa.'}
            </p>
          </div>
        )}
        {!loading && !error && filtered.length > 0 && (
          <ResponsiveTable
            columns={brocaColumns}
            data={filtered}
            keyExtractor={(b) => b.id}
            renderActions={
              canCreate
                ? (b) => (
                    <button
                      onClick={() => openEdit(b)}
                      title="Editar"
                      className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                    >
                      <Pencil className="w-4 h-4" />
                    </button>
                  )
                : undefined
            }
          />
        )}
      </div>

      <BrocaFormModal
        open={modalOpen}
        onClose={handleClose}
        onSuccess={() => load()}
        initialData={editingBroca}
      />
    </div>
  )
}
