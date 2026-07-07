import { useCallback, useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { ShieldCheck, Loader2, Plus, Pencil, Trash2 } from 'lucide-react'
import { getUsuariosSistema, deleteUsuarioSistema, type UsuarioSistema } from '@/api/usuarios'
import { UsuarioSistemaFormModal } from '@/components/UsuarioSistemaFormModal'

type Filtro = 'todos' | 'ativos' | 'inativos'

const FILTROS: { value: Filtro; label: string }[] = [
  { value: 'todos',    label: 'Todos'    },
  { value: 'ativos',   label: 'Ativos'   },
  { value: 'inativos', label: 'Inativos' },
]

export function UsuariosSistemaPage() {
  const [usuarios, setUsuarios]     = useState<UsuarioSistema[]>([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState<string | null>(null)
  const [filtro, setFiltro]         = useState<Filtro>('ativos')
  const [modalOpen, setModalOpen]   = useState(false)
  const [editTarget, setEditTarget] = useState<UsuarioSistema | undefined>(undefined)

  const load = useCallback((signal?: AbortSignal) => {
    setLoading(true)
    setError(null)

    getUsuariosSistema(signal)
      .then(setUsuarios)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) {
          setError('Não foi possível carregar os usuários.')
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
    if (filtro === 'ativos')   return usuarios.filter(u => u.ativo)
    if (filtro === 'inativos') return usuarios.filter(u => !u.ativo)
    return usuarios
  }, [usuarios, filtro])

  function openCreate() {
    setEditTarget(undefined)
    setModalOpen(true)
  }

  function openEdit(u: UsuarioSistema) {
    setEditTarget(u)
    setModalOpen(true)
  }

  function handleClose() {
    setModalOpen(false)
    setEditTarget(undefined)
  }

  async function handleDelete(u: UsuarioSistema) {
    if (!confirm(`Remover o usuário "${u.name}"?`)) return

    try {
      await deleteUsuarioSistema(u.id)
      load()
    } catch {
      setError('Não foi possível remover o usuário.')
    }
  }

  return (
    <>
      <div className="space-y-6">
        {/* cabeçalho */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-[#00aa84]/10">
              <ShieldCheck className="w-5 h-5 text-[#00aa84]" />
            </div>
            <div>
              <h1 className="text-xl font-semibold text-white">Usuários do Sistema</h1>
              <p className="text-sm text-slate-400">Gerencie quem tem acesso ao painel administrativo</p>
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
                  ? 'Nenhum usuário cadastrado.'
                  : filtro === 'ativos'
                    ? 'Nenhum usuário ativo.'
                    : 'Nenhum usuário inativo.'}
              </p>
            </div>
          )}
          {!loading && !error && filtered.length > 0 && (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-white/5 text-left">
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Nome</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">E-mail</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Perfil</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Rotinas</th>
                  <th className="px-6 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
                  <th className="px-6 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {filtered.map((u) => (
                  <tr key={u.id} className="hover:bg-white/[0.02] transition-colors">
                    <td className="px-6 py-4 font-medium text-white">{u.name}</td>
                    <td className="px-6 py-4 text-slate-400">{u.email}</td>
                    <td className="px-6 py-4">
                      {u.role === 'admin'
                        ? <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-amber-500/10 text-amber-400">Administrador</span>
                        : <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-[#00aa84]/10 text-[#00aa84]">Funcionário</span>
                      }
                    </td>
                    <td className="px-6 py-4 text-slate-400">
                      {u.role === 'admin'
                        ? <span className="text-slate-600">Acesso total</span>
                        : (u.rotinas?.length ?? 0) > 0
                          ? `${u.rotinas!.length} rotina(s)`
                          : <span className="text-slate-600">Nenhuma rotina</span>
                      }
                    </td>
                    <td className="px-6 py-4">
                      {u.ativo
                        ? <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-500/10 text-emerald-400">Ativo</span>
                        : <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-red-500/10 text-red-400">Inativo</span>
                      }
                    </td>
                    <td className="px-4 py-4 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          onClick={() => openEdit(u)}
                          className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                          title="Editar usuário"
                        >
                          <Pencil className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(u)}
                          className="p-1.5 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-400/10 transition-colors"
                          title="Remover usuário"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      <UsuarioSistemaFormModal
        open={modalOpen}
        onClose={handleClose}
        onSuccess={() => load()}
        initialData={editTarget}
      />
    </>
  )
}
