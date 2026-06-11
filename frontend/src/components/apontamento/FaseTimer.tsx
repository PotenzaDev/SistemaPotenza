import { type ReactNode } from 'react'
import { Loader2 } from 'lucide-react'
import { InfoCard } from './InfoCard'

interface FaseTimerProps {
  titulo: string
  subtitulo: string
  icone: ReactNode
  corIcone: string
  timer: string
  corTimer: string
  produto: string | null
  codPeca: string
  ordemLote: string
  qtdeTotal: number | null
  botaoLabel: string
  botaoIcone: ReactNode
  loading: boolean
  onAcao: () => void
}

export function FaseTimer({
  titulo, subtitulo, icone, corIcone, timer, corTimer,
  produto, codPeca, ordemLote, qtdeTotal, botaoLabel, botaoIcone, loading, onAcao,
}: FaseTimerProps) {
  return (
    <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
      <div className="flex items-center gap-3 px-6 pt-6 pb-4 border-b border-white/5">
        <div className={`p-2 rounded-lg ${corIcone}`}>{icone}</div>
        <div>
          <p className="text-sm font-semibold text-white">{titulo}</p>
          <p className="text-xs text-slate-500 mt-0.5">{subtitulo}</p>
        </div>
      </div>
      <div className="px-6 py-5 space-y-4">
        <div className="flex flex-col items-center py-4">
          <p className="text-xs text-slate-500 mb-1">Tempo decorrido</p>
          <p className={`text-4xl font-mono font-bold tabular-nums ${corTimer}`}>{timer}</p>
        </div>
        <div className="grid grid-cols-2 gap-3">
          {produto && (
            <div className="col-span-2 bg-white/[0.03] rounded-lg px-3 py-2.5">
              <p className="text-xs text-slate-500">Produto</p>
              <p className="text-sm font-semibold text-white mt-0.5">{produto}</p>
            </div>
          )}
          <InfoCard label="Cód. da peça" value={codPeca} />
          <InfoCard label="Ordem / Lote" value={ordemLote} />
          {qtdeTotal !== null && (
            <div className="col-span-2">
              <InfoCard label="Total do pedido" value={`${qtdeTotal} peças`} highlight />
            </div>
          )}
        </div>
        <button
          type="button"
          onClick={onAcao}
          disabled={loading}
          className="w-full py-2.5 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
        >
          {loading
            ? <><Loader2 className="w-4 h-4 animate-spin" />Aguarde…</>
            : <>{botaoIcone}{botaoLabel}</>}
        </button>
      </div>
    </div>
  )
}
