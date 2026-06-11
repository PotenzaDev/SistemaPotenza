import { Pause, X, Loader2 } from 'lucide-react'
import type { MotivoPausa } from '@/api/motivosPausa'

interface MotivoPausaModalProps {
  motivos: MotivoPausa[]
  pausando: boolean
  onSelect: (id: number) => void
  onClose: () => void
}

export function MotivoPausaModal({
  motivos, pausando, onSelect, onClose,
}: MotivoPausaModalProps) {
  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div className="w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
        <div className="flex items-center justify-between px-5 py-4 border-b border-white/5">
          <div className="flex items-center gap-2">
            <Pause className="w-4 h-4 text-amber-400" />
            <p className="text-sm font-semibold text-white">Motivo da pausa</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={pausando}
            className="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors disabled:opacity-30"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
        <div className="p-4 space-y-2 max-h-80 overflow-y-auto">
          {motivos.length === 0 ? (
            <p className="text-xs text-slate-500 text-center py-6">
              Nenhum motivo cadastrado.<br />Peça ao administrador para configurar os motivos.
            </p>
          ) : (
            motivos.map(m => (
              <button
                key={m.id}
                type="button"
                onClick={() => onSelect(m.id)}
                disabled={pausando}
                className="w-full text-left px-4 py-3 rounded-xl bg-white/[0.03] hover:bg-amber-500/10 border border-white/5 hover:border-amber-500/20 text-sm font-medium text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {pausando
                  ? <span className="flex items-center gap-2"><Loader2 className="w-3.5 h-3.5 animate-spin text-amber-400" />Pausando…</span>
                  : m.nome}
              </button>
            ))
          )}
        </div>
      </div>
    </div>
  )
}
