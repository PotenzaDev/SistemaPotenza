import { useEffect, useState } from 'react'
import { Pause, AlertTriangle, Loader2, Play } from 'lucide-react'
import type { Apontamento, Pausa } from '@/api/apontamento'

interface PausadoPanelProps {
  apontamento: Apontamento
  pausaAtual: Pausa | undefined
  saiuSemPausar: boolean
  retomando: boolean
  onRetomar: () => void
}

export function PausadoPanel({
  apontamento, pausaAtual, saiuSemPausar, retomando, onRetomar,
}: PausadoPanelProps) {
  const [tempoPausa, setTempoPausa] = useState('00:00')

  useEffect(() => {
    if (!pausaAtual || pausaAtual.fim !== null) return
    const fmt = (s: number) => {
      const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), ss = s % 60
      return h > 0
        ? `${h}:${String(m).padStart(2, '0')}:${String(ss).padStart(2, '0')}`
        : `${String(m).padStart(2, '0')}:${String(ss).padStart(2, '0')}`
    }
    const calc = () => fmt(Math.max(0, Math.floor((Date.now() - new Date(pausaAtual.inicio).getTime()) / 1000)))
    setTempoPausa(calc())
    const id = setInterval(() => setTempoPausa(calc()), 1000)
    return () => clearInterval(id)
  }, [pausaAtual?.inicio]) // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="space-y-4">
      {saiuSemPausar && (
        <div className="flex items-start gap-3 bg-amber-500/10 border border-amber-500/30 rounded-xl px-4 py-3">
          <AlertTriangle className="w-4 h-4 text-amber-400 mt-0.5 shrink-0" />
          <div>
            {pausaAtual?.motivo === 'Fim de Turno' ? (
              <>
                <p className="text-xs font-semibold text-amber-400">Retomando turno anterior</p>
                <p className="text-xs text-amber-400/70 mt-0.5">
                  Seu apontamento foi pausado no fim do turno. Retome para continuar de onde parou.
                </p>
              </>
            ) : (
              <>
                <p className="text-xs font-semibold text-amber-400">Você saiu sem pausar</p>
                <p className="text-xs text-amber-400/70 mt-0.5">
                  O sistema registrou uma pausa automática. Retome para continuar de onde parou.
                </p>
              </>
            )}
          </div>
        </div>
      )}

      <div className="bg-[#0f1923] border border-amber-500/30 rounded-xl overflow-hidden">
        <div className="flex items-center gap-3 px-6 pt-5 pb-4 border-b border-amber-500/10">
          <div className="p-2 rounded-lg bg-amber-500/10">
            <Pause className="w-5 h-5 text-amber-400" />
          </div>
          <div>
            <div className="flex items-center gap-2 flex-wrap">
              <p className="text-sm font-semibold text-white">Apontamento pausado</p>
              <span className="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/20 text-amber-400">
                Em pausa
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-0.5">
              {apontamento.cod_peca} · lote {apontamento.ordem_lote.replace(/^0+/, '')}
            </p>
          </div>
        </div>

        <div className="px-6 py-5 space-y-4">
          <div className="flex flex-col items-center py-3">
            <p className="text-xs text-slate-500 mb-1">Tempo em pausa</p>
            <p className="text-3xl font-mono font-bold tabular-nums text-amber-400">{tempoPausa}</p>
          </div>

          <div className="space-y-2">
            {apontamento.desc_peca && (
              <div className="bg-white/[0.03] rounded-lg px-3 py-2.5">
                <p className="text-xs text-slate-500">Produto</p>
                <p className="text-sm font-semibold text-white mt-0.5">{apontamento.desc_peca}</p>
              </div>
            )}
            {pausaAtual && (
              <div className="bg-amber-500/5 border border-amber-500/15 rounded-lg px-3 py-2.5">
                <p className="text-xs text-slate-500">Motivo da pausa</p>
                <p className="text-sm font-semibold text-amber-400 mt-0.5">{pausaAtual.motivo ?? '—'}</p>
              </div>
            )}
          </div>

          <button
            type="button"
            onClick={onRetomar}
            disabled={retomando}
            className="w-full py-3 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed rounded-xl transition-colors flex items-center justify-center gap-2"
          >
            {retomando
              ? <><Loader2 className="w-4 h-4 animate-spin" />Retomando…</>
              : <><Play className="w-4 h-4" />Retomar</>}
          </button>
        </div>
      </div>
    </div>
  )
}
