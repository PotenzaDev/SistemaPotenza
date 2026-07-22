import { Layers, CheckCircle2 } from 'lucide-react'
import type { ChecklistLoteItem } from '@/api/apontamentoCorte'

interface ChecklistLoteCorteProps {
  itens: ChecklistLoteItem[]
}

export function ChecklistLoteCorte({ itens }: ChecklistLoteCorteProps) {
  const pendentes = itens.filter(i => i.falta > 0).length

  return (
    <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3 border-b border-white/5">
        <Layers className="w-4 h-4 text-slate-400" />
        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
          Peças do lote ({itens.length}){pendentes > 0 && ` · faltam ${pendentes}`}
        </p>
      </div>
      <div className="max-h-64 overflow-y-auto divide-y divide-white/5">
        {itens.map(item => {
          const completo = item.falta === 0
          return (
            <div key={`${item.cod_peca}|${item.cod_produto}|${item.cor_codigo}`} className="flex items-center justify-between gap-3 px-5 py-3">
              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-xs font-mono font-semibold text-white">{item.cod_peca}</span>
                  <span className="text-xs font-mono text-slate-500">{item.cod_produto}/{item.cor_codigo}</span>
                  {completo && <CheckCircle2 className="w-3.5 h-3.5 text-[#00aa84] shrink-0" />}
                </div>
                <p className="text-xs text-slate-500 truncate">{item.desc_peca}</p>
              </div>
              <span className={`text-xs font-semibold tabular-nums shrink-0 ${completo ? 'text-[#00aa84]' : 'text-amber-400'}`}>
                {item.qtd_bipada} / {item.qtde_total} pç
                {!completo && ` · faltam ${item.falta}`}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}
