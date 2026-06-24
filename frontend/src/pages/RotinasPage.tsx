import { useCallback, useEffect, useState } from 'react'
import axios from 'axios'
import { LayoutGrid, CheckCircle2, XCircle, Loader2, Plus, Pencil, ChevronRight } from 'lucide-react'
import { getRotinas, type Rotina } from '@/api/rotinas'
import { RotinaFormModal } from '@/components/RotinaFormModal'
import { getIcon } from '@/lib/iconRegistry'
import { useAuth } from '@/hooks/useAuth'

export function RotinasPage() {
  const { user } = useAuth()

  const [rotinas, setRotinas]       = useState<Rotina[]>([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState<string | null>(null)
  const [modalOpen, setModalOpen]   = useState(false)
  const [editingRotina, setEditingRotina] = useState<Rotina | undefined>()

  const canCreate = user?.role === 'admin'

  const load = useCallback((signal?: AbortSignal) => {
    setLoading(true)
    setError(null)
    getRotinas(signal)
      .then(setRotinas)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar as rotinas.')
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

  function openCreate() {
    setEditingRotina(undefined)
    setModalOpen(true)
  }

  function openEdit(r: Rotina) {
    setEditingRotina(r)
    setModalOpen(true)
  }

  function handleClose() {
    setModalOpen(false)
    setEditingRotina(undefined)
  }

  function renderLinha(rotina: Rotina, isFilho = false) {
    const Icon = getIcon(rotina.icone)

    return (
      <tr key={rotina.id} className="hover:bg-white/[0.02] transition-colors">
        <td className="px-4 py-3">
          <div className={`flex items-center gap-2 ${isFilho ? 'pl-6 text-slate-400' : 'text-white font-medium'}`}>
            {isFilho && <ChevronRight className="w-3.5 h-3.5 text-slate-600 shrink-0" />}
            <Icon className="w-4 h-4 shrink-0" />
            {rotina.nome}
          </div>
        </td>
        <td className="px-4 py-3 text-slate-400 font-mono text-xs">{rotina.slug}</td>
        <td className="px-4 py-3 text-slate-300 font-mono text-xs">{rotina.pagina}</td>
        <td className="px-4 py-3 text-slate-300">{rotina.ordem}</td>
        <td className="px-4 py-3">
          {rotina.ativo ? (
            <span className="inline-flex items-center gap-1.5 text-[#00aa84]">
              <CheckCircle2 className="w-4 h-4" /> Ativa
            </span>
          ) : (
            <span className="inline-flex items-center gap-1.5 text-slate-500">
              <XCircle className="w-4 h-4" /> Inativa
            </span>
          )}
        </td>
        {canCreate && (
          <td className="px-4 py-3">
            <button
              onClick={() => openEdit(rotina)}
              title="Editar"
              className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
            >
              <Pencil className="w-4 h-4" />
            </button>
          </td>
        )}
      </tr>
    )
  }

  return (
    <div className="space-y-6">

      {/* cabeçalho */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <LayoutGrid className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Rotinas</h1>
            <p className="text-sm text-slate-400">Gerencie os menus e submenus do sistema</p>
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
        {!loading && !error && rotinas.length === 0 && (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-slate-500">Nenhuma rotina cadastrada.</p>
          </div>
        )}
        {!loading && !error && rotinas.length > 0 && (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-white/5 text-left">
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Nome</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Slug</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Página</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Ordem</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
                {canCreate && (
                  <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider w-16"></th>
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {rotinas.map((rotina) => [
                renderLinha(rotina),
                ...(rotina.filhos ?? []).map((filho) => renderLinha(filho, true)),
              ])}
            </tbody>
          </table>
        )}
      </div>

      <RotinaFormModal
        open={modalOpen}
        onClose={handleClose}
        onSuccess={() => load()}
        initialData={editingRotina}
        paisDisponiveis={rotinas.filter((r) => !r.parent_id)}
      />
    </div>
  )
}
