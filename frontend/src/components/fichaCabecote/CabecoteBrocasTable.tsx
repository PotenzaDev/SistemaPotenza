import { Plus, Trash2 } from 'lucide-react'
import { SENTIDO_OPTIONS, type SentidoCabecote } from '@/api/fichasCabecote'
import type { Broca } from '@/api/brocas'
import { gerarId } from '@/lib/utils'

export interface CabecoteBrocaRow {
  key: string
  cabecote: string
  sentido: SentidoCabecote | ''
  posicao: string
  broca_id: string
  passante: boolean
  profundidade_mm: string
  agregado: string
  obs: string
}

export function novaCabecoteBrocaRow(): CabecoteBrocaRow {
  return {
    key: gerarId(),
    cabecote: '',
    sentido: '',
    posicao: '',
    broca_id: '',
    passante: true,
    profundidade_mm: '',
    agregado: '',
    obs: '',
  }
}

interface Props {
  rows: CabecoteBrocaRow[]
  onChange: (rows: CabecoteBrocaRow[]) => void
  brocas: Broca[]
}

const INPUT = 'w-full px-2 py-1.5 text-xs bg-white/5 border border-white/10 rounded text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 transition-colors disabled:opacity-40 disabled:cursor-not-allowed'

export function CabecoteBrocasTable({ rows, onChange, brocas }: Props) {
  function updateRow(key: string, patch: Partial<CabecoteBrocaRow>) {
    onChange(rows.map(r => (r.key === key ? { ...r, ...patch } : r)))
  }

  function removeRow(key: string) {
    onChange(rows.filter(r => r.key !== key))
  }

  return (
    <div className="space-y-3">
      <div className="bg-white/[0.02] border border-white/5 rounded-lg overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left bg-white/[0.02]">
              <th className="px-3 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider">Cabeçote</th>
              <th className="px-3 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider">Sentido</th>
              <th className="px-3 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider">Posição</th>
              <th className="px-3 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider">Broca</th>
              <th className="px-3 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider">Passante / Prof. (mm)</th>
              <th className="px-3 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider">Agregado</th>
              <th className="px-3 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider">Obs</th>
              <th className="px-3 py-2 w-10"></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/5">
            {rows.map(row => (
              <tr key={row.key}>
                <td className="px-3 py-2">
                  <input
                    value={row.cabecote}
                    onChange={e => updateRow(row.key, { cabecote: e.target.value })}
                    className={INPUT}
                  />
                </td>
                <td className="px-3 py-2">
                  <select
                    value={row.sentido}
                    onChange={e => updateRow(row.key, { sentido: e.target.value as SentidoCabecote })}
                    className={INPUT}
                  >
                    <option value="">Selecione</option>
                    {SENTIDO_OPTIONS.map(opt => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                </td>
                <td className="px-3 py-2">
                  <input
                    value={row.posicao}
                    onChange={e => updateRow(row.key, { posicao: e.target.value })}
                    className={INPUT}
                  />
                </td>
                <td className="px-3 py-2">
                  <select
                    value={row.broca_id}
                    onChange={e => updateRow(row.key, { broca_id: e.target.value })}
                    className={INPUT}
                  >
                    <option value="">Selecione</option>
                    {brocas.map(broca => (
                      <option key={broca.id} value={broca.id}>{broca.codigo}</option>
                    ))}
                  </select>
                </td>
                <td className="px-3 py-2">
                  <div className="flex items-center gap-2">
                    <label className="flex items-center gap-1 text-xs text-slate-300 shrink-0">
                      <input
                        type="checkbox"
                        checked={row.passante}
                        onChange={e => updateRow(row.key, {
                          passante: e.target.checked,
                          profundidade_mm: e.target.checked ? '' : row.profundidade_mm,
                        })}
                      />
                      Passante
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      value={row.profundidade_mm}
                      disabled={row.passante}
                      onChange={e => updateRow(row.key, { profundidade_mm: e.target.value })}
                      placeholder="Profundidade"
                      className={INPUT}
                    />
                  </div>
                </td>
                <td className="px-3 py-2">
                  <input
                    value={row.agregado}
                    onChange={e => updateRow(row.key, { agregado: e.target.value })}
                    className={INPUT}
                  />
                </td>
                <td className="px-3 py-2">
                  <input
                    value={row.obs}
                    onChange={e => updateRow(row.key, { obs: e.target.value })}
                    className={INPUT}
                  />
                </td>
                <td className="px-3 py-2 text-center">
                  <button
                    type="button"
                    onClick={() => removeRow(row.key)}
                    disabled={rows.length <= 1}
                    title="Remover linha"
                    className="p-1 rounded text-slate-400 hover:text-red-400 hover:bg-white/10 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <button
        type="button"
        onClick={() => onChange([...rows, novaCabecoteBrocaRow()])}
        className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-[#00aa84] hover:bg-[#00aa84]/10 rounded-lg transition-colors"
      >
        <Plus className="w-3.5 h-3.5" />
        Adicionar linha
      </button>
    </div>
  )
}
