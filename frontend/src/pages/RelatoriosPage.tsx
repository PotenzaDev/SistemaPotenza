import { Link } from 'react-router-dom'
import { FileBarChart, Cpu, Clock, type LucideIcon } from 'lucide-react'

interface RelatorioInfo {
  to: string
  title: string
  description: string
  icon: LucideIcon
}

const RELATORIOS: RelatorioInfo[] = [
  {
    to: '/admin/relatorios/producao-maquinas',
    title: 'Produção de Máquinas',
    description: 'Tempo de utilização, setup, parada e quantidade de peças produzidas por máquina em um período.',
    icon: Cpu,
  },
  {
    to: '/admin/relatorios/timeline-maquinas',
    title: 'Linha do Tempo de Máquinas',
    description: 'Setup, produção, pausa e parado de cada máquina ao longo do turno, minuto a minuto.',
    icon: Clock,
  },
]

export function RelatoriosPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <div className="p-2 rounded-lg bg-[#00aa84]/10">
          <FileBarChart className="w-5 h-5 text-[#00aa84]" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-white">Relatórios</h1>
          <p className="text-sm text-slate-400">Selecione um relatório para visualizar</p>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {RELATORIOS.map(({ to, title, description, icon: Icon }) => (
          <Link
            key={to}
            to={to}
            className="bg-[#0f1923] border border-white/5 rounded-xl p-5 hover:border-[#00aa84]/40 hover:bg-[#00aa84]/5 transition-colors"
          >
            <div className="p-2 rounded-lg bg-[#00aa84]/10 w-fit mb-3">
              <Icon className="w-5 h-5 text-[#00aa84]" />
            </div>
            <h2 className="text-sm font-semibold text-white mb-1">{title}</h2>
            <p className="text-xs text-slate-400">{description}</p>
          </Link>
        ))}
      </div>
    </div>
  )
}
