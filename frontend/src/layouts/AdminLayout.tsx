import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import { Menu, X } from 'lucide-react'
import { Sidebar } from '@/components/Sidebar'

const DESKTOP_SIDEBAR_KEY = 'sidebar-desktop-open'

function getInitialDesktopOpen(): boolean {
  const stored = localStorage.getItem(DESKTOP_SIDEBAR_KEY)
  return stored !== null ? stored === 'true' : true
}

export function AdminLayout() {
  const [mobileOpen, setMobileOpen] = useState(false)
  const [desktopOpen, setDesktopOpen] = useState(getInitialDesktopOpen)

  function toggleDesktopOpen() {
    setDesktopOpen((current) => {
      const next = !current
      localStorage.setItem(DESKTOP_SIDEBAR_KEY, String(next))
      return next
    })
  }

  return (
    <div className="flex h-screen bg-[#111820] overflow-hidden">

      {/* Sidebar desktop — pode ser escondido pelo usuário */}
      <div
        className={`hidden md:flex flex-shrink-0 overflow-hidden transition-all duration-300 ${
          desktopOpen ? 'w-64' : 'w-0'
        }`}
      >
        <Sidebar />
      </div>

      {/* Sidebar mobile — overlay */}
      {mobileOpen && (
        <>
          <div
            className="fixed inset-0 bg-black/60 z-30 md:hidden"
            onClick={() => setMobileOpen(false)}
          />
          <div className="fixed inset-y-0 left-0 z-40 md:hidden">
            <Sidebar onClose={() => setMobileOpen(false)} />
          </div>
        </>
      )}

      {/* Área principal */}
      <div className="flex-1 flex flex-col min-w-0 overflow-hidden">

        {/* Topbar mobile */}
        <header className="md:hidden flex items-center gap-3 px-4 py-3 bg-[#0f1923] border-b border-white/5">
          <button
            onClick={() => setMobileOpen(true)}
            className="text-slate-400 hover:text-white transition-colors"
            aria-label="Abrir menu"
          >
            {mobileOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
          </button>
          <img src="/logo.png" alt="Potenza" className="h-7 w-auto object-contain" />
        </header>

        {/* Topbar desktop — botão para esconder/mostrar o menu lateral */}
        <header className="hidden md:flex items-center gap-3 px-4 py-3 bg-[#0f1923] border-b border-white/5">
          <button
            onClick={toggleDesktopOpen}
            className="text-slate-400 hover:text-white transition-colors"
            aria-label={desktopOpen ? 'Esconder menu' : 'Mostrar menu'}
            title={desktopOpen ? 'Esconder menu' : 'Mostrar menu'}
          >
            <Menu className="w-5 h-5" />
          </button>
        </header>

        {/* Conteúdo da página */}
        <main className="flex-1 overflow-y-auto p-4 sm:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
