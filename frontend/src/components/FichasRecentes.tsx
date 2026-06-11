import { Layers, Clock } from 'lucide-react'
import type { FichaApontamento } from '@/api/apontamento'

interface Props {
  fichas: FichaApontamento[]
}

export function FichasRecentes({ fichas }: Props) {
  if (fichas.length === 0) return null

  return (
    <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3 border-b border-white/5">
        <Layers className="w-4 h-4 text-slate-400" />
        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
          Fichas recentes
        </p>
      </div>

      <div className="divide-y divide-white/5">
        {fichas.map((ficha) => (
          <FichaRow key={ficha.id} ficha={ficha} />
        ))}
      </div>
    </div>
  )
}

function FichaRow({ ficha }: { ficha: FichaApontamento }) {
  const pilhaLabel = ficha.total_pilhas > 0
    ? `${ficha.pilha} / ${ficha.total_pilhas}`
    : String(ficha.pilha)

  return (
    <div className="flex items-center justify-between gap-3 px-5 py-3">
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2 flex-wrap">
          <span className="text-xs font-mono font-semibold text-white">
            {ficha.cod_peca}
          </span>
          {ficha.ordem_lote && (
            <span className="text-xs text-slate-500">
              lote {ficha.ordem_lote.replace(/^0+/, '')}
            </span>
          )}
        </div>
        <div className="flex items-center gap-1 mt-0.5">
          <Clock className="w-3 h-3 text-slate-600" />
          <span className="text-xs text-slate-600">
            {new Date(ficha.bipada_at).toLocaleTimeString('pt-BR', {
              hour: '2-digit',
              minute: '2-digit',
            })}
          </span>
        </div>
      </div>

      <div className="flex items-center gap-3 shrink-0">
        <div className="text-right">
          <p className="text-xs text-slate-500">Qtd</p>
          <p className="text-sm font-semibold text-white">{ficha.qtd_peca}</p>
        </div>

        <div className="bg-[#00aa84]/10 border border-[#00aa84]/20 rounded-lg px-3 py-1.5 text-center min-w-[64px]">
          <p className="text-xs text-slate-500 leading-none mb-0.5">Pilha</p>
          <p className="text-sm font-bold text-[#00aa84] tabular-nums">{pilhaLabel}</p>
        </div>
      </div>
    </div>
  )
}
