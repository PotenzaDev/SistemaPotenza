interface InfoCardProps {
  label: string
  value: string
  highlight?: boolean
}

export function InfoCard({ label, value, highlight = false }: InfoCardProps) {
  return (
    <div className="bg-white/[0.03] rounded-lg px-3 py-2.5 space-y-0.5">
      <p className="text-xs text-slate-500">{label}</p>
      <p className={`text-sm font-semibold ${highlight ? 'text-[#00aa84]' : 'text-white'}`}>{value}</p>
    </div>
  )
}
