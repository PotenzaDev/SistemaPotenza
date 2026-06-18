import { NavLink, useNavigate } from 'react-router-dom'
import { Cpu, Users, ClipboardList, LogOut, PauseCircle, LayoutDashboard, Clock, FileBarChart, UserCircle, History } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'

const NAV_ITEMS = [
  { to: '/admin/dashboard',     label: 'Dashboard',     icon: LayoutDashboard },
  { to: '/admin/maquinas',      label: 'Máquinas',      icon: Cpu },
  { to: '/admin/operarios',     label: 'Operários',     icon: Users },
  { to: '/admin/apontamentos',  label: 'Apontamentos',  icon: ClipboardList },
  { to: '/admin/motivos-pausa', label: 'Mot. de Pausa', icon: PauseCircle },
  { to: '/admin/turnos',        label: 'Turnos',        icon: Clock },
  { to: '/admin/relatorios',    label: 'Relatórios',    icon: FileBarChart },
  { to: '/admin/logs',          label: 'Log de Atividades', icon: History },
  { to: '/admin/perfil',        label: 'Meu Perfil',    icon: UserCircle },
]

interface SidebarProps {
  onClose?: () => void
}

export function Sidebar({ onClose }: SidebarProps) {
  const { signOut, user } = useAuth()
  const navigate = useNavigate()

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
        {NAV_ITEMS.map(({ to, label, icon: Icon }) => (
          <NavLink
            key={to}
            to={to}
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
            {label}
          </NavLink>
        ))}
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
