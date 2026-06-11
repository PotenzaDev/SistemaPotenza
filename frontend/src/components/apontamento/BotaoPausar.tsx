import { Pause } from 'lucide-react'

interface BotaoPausarProps {
  label: string
  disabled: boolean
  onClick: () => void
}

export function BotaoPausar({ label, disabled, onClick }: BotaoPausarProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className="w-full py-2.5 text-sm font-semibold text-amber-400 bg-amber-500/10 hover:bg-amber-500/15 disabled:opacity-30 disabled:cursor-not-allowed rounded-xl border border-amber-500/20 transition-colors flex items-center justify-center gap-2"
    >
      <Pause className="w-4 h-4" />
      {label}
    </button>
  )
}
