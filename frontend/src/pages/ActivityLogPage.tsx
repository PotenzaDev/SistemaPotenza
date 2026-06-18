import { useEffect, useState, useCallback } from 'react'
import { Search, ChevronLeft, ChevronRight, AlertCircle, RefreshCw } from 'lucide-react'
import { getActivityLogs, type ActivityLog, type ActivityLogFilters } from '@/api/activityLogs'

const ACTION_LABELS: Record<string, string> = {
  login:            'Login',
  logout:           'Logout',
  trocar_senha:     'Troca de senha',
  atualizar_perfil: 'Atualização de perfil',
  criar_operario:   'Criar operário',
  editar_operario:  'Editar operário',
  remover_operario: 'Remover operário',
}

const ACTION_COLORS: Record<string, string> = {
  login:            'text-emerald-400 bg-emerald-400/10',
  logout:           'text-slate-400 bg-slate-400/10',
  trocar_senha:     'text-yellow-400 bg-yellow-400/10',
  atualizar_perfil: 'text-blue-400 bg-blue-400/10',
  criar_operario:   'text-cyan-400 bg-cyan-400/10',
  editar_operario:  'text-orange-400 bg-orange-400/10',
  remover_operario: 'text-red-400 bg-red-400/10',
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

export function ActivityLogPage() {
  const today = new Date().toISOString().slice(0, 10)

  const [logs, setLogs]         = useState<ActivityLog[]>([])
  const [loading, setLoading]   = useState(true)
  const [erroApi, setErroApi]   = useState<string | null>(null)
  const [page, setPage]         = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal]       = useState(0)
  const [from, setFrom]         = useState(today)
  const [to, setTo]             = useState(today)
  const [action, setAction]     = useState('')

  const carregar = useCallback(async (p: number) => {
    setLoading(true)
    setErroApi(null)
    try {
      const filters: ActivityLogFilters = { from, to, per_page: 50, page: p }
      if (action) filters.action = action
      const result = await getActivityLogs(filters)
      setLogs(result.data)
      setLastPage(result.last_page)
      setTotal(result.total)
      setPage(p)
    } catch {
      setErroApi('Não foi possível carregar os logs.')
    } finally {
      setLoading(false)
    }
  }, [from, to, action])

  useEffect(() => { carregar(1) }, [carregar])

  return (
    <div className="space-y-5 p-6">

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold text-white">Log de Atividades</h1>
          <p className="text-sm text-slate-400 mt-0.5">{total} registros encontrados</p>
        </div>
        <button
          onClick={() => carregar(1)}
          className="flex items-center gap-1.5 px-3 py-1.5 text-xs text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition-colors"
        >
          <RefreshCw className="w-3.5 h-3.5" />
          Atualizar
        </button>
      </div>

      {/* Filtros */}
      <div className="flex flex-wrap gap-3 bg-white/5 border border-white/10 rounded-xl p-4">
        <div className="flex flex-col gap-1">
          <label className="text-xs text-white/50">De</label>
          <input type="date" value={from} onChange={e => setFrom(e.target.value)}
            className="px-3 py-1.5 text-sm bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:border-[#00aa84] transition-colors" />
        </div>
        <div className="flex flex-col gap-1">
          <label className="text-xs text-white/50">Até</label>
          <input type="date" value={to} onChange={e => setTo(e.target.value)}
            className="px-3 py-1.5 text-sm bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:border-[#00aa84] transition-colors" />
        </div>
        <div className="flex flex-col gap-1">
          <label className="text-xs text-white/50">Ação</label>
          <select value={action} onChange={e => setAction(e.target.value)}
            className="px-3 py-1.5 text-sm bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:border-[#00aa84] transition-colors">
            <option value="">Todas</option>
            {Object.entries(ACTION_LABELS).map(([v, l]) => (
              <option key={v} value={v}>{l}</option>
            ))}
          </select>
        </div>
        <div className="flex items-end">
          <button onClick={() => carregar(1)}
            className="flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium text-white rounded-lg transition-all"
            style={{ backgroundColor: '#00aa84' }}>
            <Search className="w-4 h-4" />
            Buscar
          </button>
        </div>
      </div>

      {erroApi && (
        <div className="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
          <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
          <p className="text-xs text-red-400">{erroApi}</p>
        </div>
      )}

      <div className="bg-white/5 border border-white/10 rounded-xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-white/10 text-left">
                <th className="px-4 py-3 text-xs font-medium text-slate-500 uppercase tracking-wide">Data/Hora</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-500 uppercase tracking-wide">Usuário</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-500 uppercase tracking-wide">Ação</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-500 uppercase tracking-wide">Descrição</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-500 uppercase tracking-wide">IP</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {loading ? (
                <tr><td colSpan={5} className="px-4 py-8 text-center text-slate-500">Carregando…</td></tr>
              ) : logs.length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-8 text-center text-slate-500">Nenhum registro encontrado.</td></tr>
              ) : logs.map(log => (
                <tr key={log.id} className="hover:bg-white/5 transition-colors">
                  <td className="px-4 py-3 text-slate-300 whitespace-nowrap">{formatDate(log.created_at)}</td>
                  <td className="px-4 py-3 text-white font-medium">{log.user_name}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${ACTION_COLORS[log.action] ?? 'text-slate-400 bg-slate-400/10'}`}>
                      {ACTION_LABELS[log.action] ?? log.action}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-400 max-w-xs truncate">{log.description}</td>
                  <td className="px-4 py-3 text-slate-500 text-xs font-mono">{log.ip_address ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {lastPage > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-white/10">
            <span className="text-xs text-slate-500">Página {page} de {lastPage}</span>
            <div className="flex gap-2">
              <button onClick={() => carregar(page - 1)} disabled={page <= 1 || loading}
                className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                <ChevronLeft className="w-4 h-4" />
              </button>
              <button onClick={() => carregar(page + 1)} disabled={page >= lastPage || loading}
                className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
