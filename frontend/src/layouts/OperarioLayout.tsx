import { Outlet, useNavigate } from 'react-router-dom'
import { LogOut } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'

export function OperarioLayout() {
  const { user, signOut } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    await signOut()
    navigate('/login', { replace: true })
  }

  return (
    <div className="min-h-screen bg-[#0a1520] flex flex-col">

      {/* Header */}
      <header className="bg-[#0f1923] border-b border-white/5 px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
        <img
          src="/logo.png"
          alt="Potenza"
          className="h-8 w-auto object-contain shrink-0"
        />

        <div className="flex items-center gap-2 sm:gap-4 min-w-0">
          <div className="text-right min-w-0">
            <p className="text-sm font-medium text-white leading-tight truncate max-w-[120px] sm:max-w-none">{user?.name}</p>
            <p className="text-xs text-slate-500 leading-tight">Operário</p>
          </div>
          <button
            onClick={handleLogout}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-white/10 transition-colors shrink-0"
            title="Sair do sistema"
          >
            <LogOut className="w-4 h-4" />
            <span className="hidden sm:inline">Sair</span>
          </button>
        </div>
      </header>

      {/* Conteúdo */}
      <main className="flex-1 p-4 sm:p-6">
        <Outlet />
      </main>
    </div>
  )
}
