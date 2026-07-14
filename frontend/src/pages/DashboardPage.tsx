import { useCallback, useEffect, useState } from 'react'
import { LayoutDashboard, Package, Cpu, Clock, PauseCircle, RefreshCw } from 'lucide-react'
import {
  LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, Legend,
} from 'recharts'
import { getDashboard, type DashboardData, type MaquinaDashboard } from '@/api/dashboard'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

const STATUS_CONFIG: Record<MaquinaDashboard['status'], { label: string; dot: string; text: string }> = {
  livre: { label: 'Livre', dot: 'bg-slate-500', text: 'text-slate-400' },
  em_setup: { label: 'Setup', dot: 'bg-blue-500', text: 'text-blue-400' },
  aguardando_producao: { label: 'Aguardando', dot: 'bg-yellow-500', text: 'text-yellow-400' },
  em_producao: { label: 'Produção', dot: 'bg-[#00aa84]', text: 'text-[#00aa84]' },
  em_pausa_setup: { label: 'Pausa Setup', dot: 'bg-orange-500', text: 'text-orange-400' },
  em_pausa_aguardando: { label: 'Pausa', dot: 'bg-orange-500', text: 'text-orange-400' },
  em_pausa_producao: { label: 'Pausa Prod.', dot: 'bg-orange-500', text: 'text-orange-400' },
  pausa_ociosa: { label: 'Pausa', dot: 'bg-orange-500', text: 'text-orange-400' },
}

const PIE_COLORS = ['#00aa84', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899']

function fmt(min: number | null): string {
  if (min === null) return '—'
  if (min < 60) return `${min}min`
  return `${Math.floor(min / 60)}h${String(min % 60).padStart(2, '0')}`
}

const MAQUINA_HEADER_CLASS_FIRST = 'px-5 py-3 text-xs text-slate-500 uppercase'
const MAQUINA_HEADER_CLASS = 'px-4 py-3 text-xs text-slate-500 uppercase'

const maquinaColumns: ResponsiveTableColumn<MaquinaDashboard>[] = [
  {
    key: 'nome',
    header: 'Máquina',
    render: (m) => m.nome,
    headerClassName: MAQUINA_HEADER_CLASS_FIRST,
    cellClassName: 'px-5 py-3 font-medium text-white',
  },
  {
    key: 'status',
    header: 'Status',
    render: (m) => {
      const cfg = STATUS_CONFIG[m.status]
      return (
        <span className={`flex items-center gap-1.5 ${cfg.text}`}>
          <span className={`w-1.5 h-1.5 rounded-full ${cfg.dot}`} />
          {cfg.label}
        </span>
      )
    },
    headerClassName: MAQUINA_HEADER_CLASS,
    cellClassName: 'px-4 py-3',
  },
  {
    key: 'operario',
    header: 'Operário',
    render: (m) => m.operario ?? <span className="text-slate-600">—</span>,
    headerClassName: MAQUINA_HEADER_CLASS,
    cellClassName: 'px-4 py-3 text-slate-300',
  },
  {
    key: 'lote',
    header: 'Lote / Peça',
    render: (m) =>
      m.lote
        ? <div><p className="text-white">{m.lote}</p><p className="text-slate-500 text-xs">{m.cod_peca}</p></div>
        : <span className="text-slate-600">—</span>,
    headerClassName: MAQUINA_HEADER_CLASS,
    cellClassName: 'px-4 py-3',
  },
  {
    key: 'qtde_total',
    header: 'Qtd',
    render: (m) => m.qtde_total ?? <span className="text-slate-600">—</span>,
    headerClassName: MAQUINA_HEADER_CLASS,
    cellClassName: 'px-4 py-3 text-slate-300',
  },
  {
    key: 'setup_duracao_min',
    header: 'Setup',
    render: (m) => fmt(m.setup_duracao_min),
    headerClassName: MAQUINA_HEADER_CLASS,
    cellClassName: 'px-4 py-3 text-slate-300',
  },
  {
    key: 'producao_duracao_min',
    header: 'Produção',
    render: (m) => fmt(m.producao_duracao_min),
    headerClassName: MAQUINA_HEADER_CLASS,
    cellClassName: 'px-4 py-3 text-slate-300',
  },
  {
    key: 'total_pausa_min',
    header: 'Pausas',
    render: (m) => fmt(m.total_pausa_min),
    headerClassName: MAQUINA_HEADER_CLASS,
    cellClassName: 'px-4 py-3 text-orange-400',
  },
  {
    key: 'inicio',
    header: 'Início',
    render: (m) => m.inicio ?? '—',
    headerClassName: MAQUINA_HEADER_CLASS,
    cellClassName: 'px-4 py-3 text-slate-400',
  },
]


function KpiCard({ icon, label, value, color }: { icon: React.ReactNode; label: string; value: string; color: string }) {
  return (
    <div className="bg-white/5 border border-white/10 rounded-xl p-5 space-y-3">
      <div className={`${color} opacity-80`}>{icon}</div>
      <div>
        <p className="text-2xl font-bold text-white">{value}</p>
        <p className="text-xs text-slate-500 mt-0.5">{label}</p>
      </div>
    </div>
  )
}

export function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [lastUpdate, setLastUpdate] = useState<Date | null>(null)

  const load = useCallback(async () => {
    const result = await getDashboard()
    if (result) { setData(result); setLastUpdate(new Date()) }
    setLoading(false)
  }, [])

  useEffect(() => {
    load()
    const id = setInterval(load, 30_000)
    return () => clearInterval(id)
  }, [load])

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64 text-slate-400">
        <RefreshCw className="w-5 h-5 animate-spin mr-2" /> Carregando…
      </div>
    )
  }

  if (!data) {
    return <div className="text-red-400 text-sm p-6">Não foi possível carregar o dashboard.</div>
  }

  const { kpis, maquinas, producao_por_hora, pausas_por_motivo } = data

  return (
    <div className="space-y-6">

      {/* Cabeçalho */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <LayoutDashboard className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Dashboard</h1>
            <p className="text-sm text-slate-400">Visão geral da produção de hoje</p>
          </div>
        </div>
        {lastUpdate && (
          <span className="text-xs text-slate-500">
            Atualizado às {lastUpdate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
          </span>
        )}
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <KpiCard icon={<Package className="w-5 h-5" />} label="Peças hoje" value={kpis.pecas_hoje.toLocaleString('pt-BR')} color="text-[#00aa84]" />
        <KpiCard icon={<Cpu className="w-5 h-5" />} label="Máquinas ativas" value={String(kpis.maquinas_ativas)} color="text-blue-400" />
        <KpiCard icon={<Clock className="w-5 h-5" />} label="Apontamentos finalizados" value={String(kpis.apontamentos_finalizados_hoje)} color="text-purple-400" />
        <KpiCard icon={<PauseCircle className="w-5 h-5" />} label="Total em pausa hoje" value={fmt(kpis.total_pausa_minutos_hoje)} color="text-orange-400" />
      </div>

      {/* Tabela de máquinas */}
      <div className="bg-white/5 border border-white/10 rounded-xl overflow-hidden">
        <div className="px-5 py-4 border-b border-white/10">
          <h2 className="text-sm font-semibold text-white">Estado das Máquinas</h2>
        </div>
        <ResponsiveTable columns={maquinaColumns} data={maquinas} keyExtractor={(m) => m.id} />
      </div>

      {/* Gráficos */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="bg-white/5 border border-white/10 rounded-xl p-5">
          <h2 className="text-sm font-semibold text-white mb-4">Produção por hora</h2>
          {producao_por_hora.length === 0 ? (
            <p className="text-slate-500 text-sm text-center py-8">Sem dados de hoje</p>
          ) : (
            <ResponsiveContainer width="100%" height={200}>
              <LineChart data={producao_por_hora} margin={{ top: 4, right: 8, left: -20, bottom: 0 }}>
                <XAxis dataKey="hora" tick={{ fill: '#64748b', fontSize: 11 }} />
                <YAxis tick={{ fill: '#64748b', fontSize: 11 }} />
                <Tooltip contentStyle={{ background: '#0f1923', border: '1px solid rgba(255,255,255,0.1)', borderRadius: 8 }} labelStyle={{ color: '#94a3b8' }} itemStyle={{ color: '#00aa84' }} />
                <Line type="monotone" dataKey="pecas" stroke="#00aa84" strokeWidth={2} dot={{ fill: '#00aa84', r: 3 }} name="Peças" />
              </LineChart>
            </ResponsiveContainer>
          )}
        </div>

        <div className="bg-white/5 border border-white/10 rounded-xl p-5">
          <h2 className="text-sm font-semibold text-white mb-4">Pausas por motivo</h2>
          {pausas_por_motivo.length === 0 ? (
            <p className="text-slate-500 text-sm text-center py-8">Sem pausas hoje</p>
          ) : (
            <ResponsiveContainer width="100%" height={200}>
              <PieChart>
                <Pie data={pausas_por_motivo} dataKey="total_min" nameKey="motivo" cx="50%" cy="50%" outerRadius={70}>
                  {pausas_por_motivo.map((_, i) => (
                    <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip contentStyle={{ background: '#0f1923', border: '1px solid rgba(255,255,255,0.1)', borderRadius: 8 }} formatter={(v) => [`${v}min`, 'Tempo']} />
                <Legend wrapperStyle={{ fontSize: 12, color: '#94a3b8' }} />
              </PieChart>
            </ResponsiveContainer>
          )}
        </div>
      </div>
    </div>
  )
}
