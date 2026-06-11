import { type ReactNode } from 'react'

interface KpiCardProps {
  icon: ReactNode
  label: string
  value: string
  color: string
}

export function KpiCard({ icon, label, value, color }: KpiCardProps) {
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
