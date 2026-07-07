import { Layers, Palette } from 'lucide-react'
import type { FichaApontamento, ResumoFichasPorCor } from '@/api/apontamento'

interface FichasDoLoteProps {
  fichas: FichaApontamento[]
  resumoPorCor: ResumoFichasPorCor[]
}

export function FichasDoLote({ fichas, resumoPorCor }: FichasDoLoteProps) {
  return (
    <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3 border-b border-white/5">
        <Layers className="w-4 h-4 text-slate-400" />
        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
          Fichas deste lote ({fichas.length})
        </p>
      </div>
      {resumoPorCor.length > 1 && (
        <div className="grid grid-cols-1 gap-2 px-5 py-3 border-b border-white/5 bg-white/[0.02]">
          {resumoPorCor.map(r => (
            <div key={r.cod_peca} className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-2 min-w-0">
                <Palette className="w-3.5 h-3.5 text-slate-500 shrink-0" />
                <span className="text-xs font-medium text-white truncate">{r.cor}</span>
                <span className="text-[10px] font-mono text-slate-600 shrink-0">{r.cod_peca}</span>
              </div>
              <span className={`text-xs font-semibold tabular-nums shrink-0 ${r.falta > 0 ? 'text-amber-400' : 'text-[#00aa84]'}`}>
                {r.qtd_bipada} / {r.qtde_total} pç
                {r.falta > 0 ? ` · faltam ${r.falta}` : ''}
              </span>
            </div>
          ))}
        </div>
      )}
      <div className="max-h-52 overflow-y-auto divide-y divide-white/5">
        {fichas.map(f => {
          const totalPilhas = f.total_pilhas
          return (
            <div key={f.id} className="flex items-center justify-between gap-3 px-5 py-3">
              <div>
                <span className="text-xs font-mono font-semibold text-white">{f.cod_peca}</span>
                <span className="ml-2 text-xs text-slate-500">{f.qtd_peca} pç</span>
              </div>
              <div className="bg-[#00aa84]/10 border border-[#00aa84]/20 rounded-lg px-3 py-1.5 text-center min-w-[64px]">
                <p className="text-xs text-slate-500 leading-none mb-0.5">Pilha</p>
                <p className="text-sm font-bold text-[#00aa84] tabular-nums">
                  {totalPilhas > 0 ? `${f.pilha} / ${totalPilhas}` : String(f.pilha)}
                </p>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
