import { useCallback, useEffect, useState } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import axios from 'axios'
import { LogOut, UserCircle, ChevronDown, Loader2 } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { getMenu, type Rotina } from '@/api/rotinas'
import { getIcon } from '@/lib/iconRegistry'

interface SidebarProps {
  onClose?: () => void
}

export function Sidebar({ onClose }: SidebarProps) {
  const { signOut, user } = useAuth()
  const navigate = useNavigate()

  const [rotinas, setRotinas] = useState<Rotina[]>([])
  const [loading, setLoading] = useState(true)
  const [openParentId, setOpenParentId] = useState<number | null>(null)

  const load = useCallback((signal?: AbortSignal) => {
    setLoading(true)

    getMenu(signal)
      .then(setRotinas)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) {
          setRotinas([])
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

  function podeAcessarRotina(slug: string): boolean {
    if (user?.role !== 'funcionario') return true
    return user?.rotinas?.some((r) => r.slug === slug) ?? false
  }

  const visibleRotinas = rotinas
    .map((rotina) => ({
      ...rotina,
      filhos: rotina.filhos?.filter((filho) => podeAcessarRotina(filho.slug)) ?? [],
    }))
    .filter((rotina) => podeAcessarRotina(rotina.slug) || rotina.filhos.length > 0)

  function toggleParent(id: number) {
    setOpenParentId((current) => (current === id ? null : id))
  }

  async function handleLogout() {
    await signOut()
    navigate('/login', { replace: true })
  }

  return (
    <aside className="flex flex-col h-full w-64 bg-[#0f1923] border-r border-white/5">

      {/* Logo */}
      <div className="flex items-center justify-center px-6 py-6 border-b border-white/5">
        <img src="/logo.png" alt="Potenza" className="h-10 w-auto object-contain" />
      </div>

      {/* Nav */}
      <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        {loading && (
          <div className="flex items-center justify-center gap-2 py-8 text-slate-500">
            <Loader2 className="w-4 h-4 animate-spin" />
            <span className="text-xs">Carregando menu…</span>
          </div>
        )}

        {!loading && visibleRotinas.map((rotina) => {
          const Icon = getIcon(rotina.icone)
          const temFilhos = rotina.filhos.length > 0

          if (!temFilhos) {
            return (
              <NavLink
                key={rotina.id}
                to={rotina.pagina ?? '#'}
                onClick={onClose}
                className={({ isActive }) =>
                  [
                    'flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors',
                    isActive
                      ? 'bg-[#00aa84]/15 text-[#00aa84]'
                      : 'text-slate-400 hover:bg-white/5 hover:text-white',
                  ].join(' ')
                }
              >
                <Icon className="w-4 h-4 shrink-0" />
                {rotina.nome}
              </NavLink>
            )
          }

          const isOpen = openParentId === rotina.id

          return (
            <div key={rotina.id}>
              <button
                type="button"
                onClick={() => toggleParent(rotina.id)}
                className="flex items-center gap-3 w-full px-4 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white transition-colors"
              >
                <Icon className="w-4 h-4 shrink-0" />
                <span className="flex-1 text-left">{rotina.nome}</span>
                <ChevronDown className={`w-4 h-4 shrink-0 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
              </button>

              {isOpen && (
                <div className="mt-1 ml-4 space-y-1 border-l border-white/5 pl-3">
                  {rotina.filhos.map((filho) => {
                    const FilhoIcon = getIcon(filho.icone)
                    return (
                      <NavLink
                        key={filho.id}
                        to={filho.pagina ?? '#'}
                        onClick={onClose}
                        className={({ isActive }) =>
                          [
                            'flex items-center gap-3 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                            isActive
                              ? 'bg-[#00aa84]/15 text-[#00aa84]'
                              : 'text-slate-400 hover:bg-white/5 hover:text-white',
                          ].join(' ')
                        }
                      >
                        <FilhoIcon className="w-4 h-4 shrink-0" />
                        {filho.nome}
                      </NavLink>
                    )
                  })}
                </div>
              )}
            </div>
          )
        })}

        <NavLink
          to="/admin/perfil"
          onClick={onClose}
          className={({ isActive }) =>
            [
              'flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors',
              isActive
                ? 'bg-[#00aa84]/15 text-[#00aa84]'
                : 'text-slate-400 hover:bg-white/5 hover:text-white',
            ].join(' ')
          }
        >
          <UserCircle className="w-4 h-4 shrink-0" />
          Meu Perfil
        </NavLink>
      </nav>

      {/* Footer: usuário + logout */}
      <div className="px-3 py-4 border-t border-white/5 space-y-1">
        <div className="px-4 py-2">
          <p className="text-xs text-slate-500">Logado como</p>
          <p className="text-sm text-white font-medium truncate">{user?.name ?? '—'}</p>
          <p className="text-xs text-[#00aa84] capitalize">{user?.role}</p>
        </div>
        <button
          onClick={handleLogout}
          className="flex items-center gap-3 w-full px-4 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-red-400 transition-colors"
        >
          <LogOut className="w-4 h-4 shrink-0" />
          Sair
        </button>
      </div>
    </aside>
  )
}
