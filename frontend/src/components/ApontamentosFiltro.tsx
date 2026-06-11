import { useEffect, useState } from 'react'
import axios from 'axios'
import { getOperarios, type Operario } from '@/api/operarios'
import { getMaquinas, type Maquina } from '@/api/maquinas'
import type { ApontamentoFiltros } from '@/api/apontamentos'

interface Props {
  value: ApontamentoFiltros
  onChange: (next: ApontamentoFiltros) => void
}

const INPUT_CLASS =
  'w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white ' +
  'placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors'

const SELECT_CLASS =
  'w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white ' +
  'focus:outline-none focus:border-[#00aa84]/60 transition-colors'

export function ApontamentosFiltro({ value, onChange }: Props) {
  const [operarios, setOperarios] = useState<Operario[]>([])
  const [maquinas, setMaquinas]   = useState<Maquina[]>([])
  const [ordemLote, setOrdemLote] = useState(value.ordemLote ?? '')

  useEffect(() => {
    const controller = new AbortController()

    Promise.all([getOperarios(controller.signal), getMaquinas(controller.signal)])
      .then(([op, maq]) => {
        setOperarios(op)
        setMaquinas(maq)
      })
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) {
          // Silencioso: filtros de operário/máquina ficam apenas com a opção "Todos"
        }
      })

    return () => controller.abort()
  }, [])

  /* debounce do campo de texto para não disparar uma busca a cada tecla */
  useEffect(() => {
    const timer = setTimeout(() => {
      if (ordemLote !== (value.ordemLote ?? '')) {
        onChange({ ...value, ordemLote: ordemLote || undefined })
      }
    }, 400)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ordemLote])

  return (
    <div className="bg-[#0f1923] border border-white/5 rounded-xl p-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div>
          <label className="block text-xs font-medium text-slate-400 mb-1.5">De</label>
          <input
            type="date"
            value={value.dataInicio ?? ''}
            onChange={e => onChange({ ...value, dataInicio: e.target.value || undefined })}
            className={INPUT_CLASS}
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-slate-400 mb-1.5">Até</label>
          <input
            type="date"
            value={value.dataFim ?? ''}
            onChange={e => onChange({ ...value, dataFim: e.target.value || undefined })}
            className={INPUT_CLASS}
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-slate-400 mb-1.5">Operário</label>
          <select
            value={value.operarioId ?? ''}
            onChange={e => onChange({ ...value, operarioId: e.target.value ? Number(e.target.value) : undefined })}
            className={SELECT_CLASS}
          >
            <option value="">Todos</option>
            {operarios.map(o => (
              <option key={o.id} value={o.id}>{o.user.name}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-slate-400 mb-1.5">Máquina</label>
          <select
            value={value.maquinaId ?? ''}
            onChange={e => onChange({ ...value, maquinaId: e.target.value ? Number(e.target.value) : undefined })}
            className={SELECT_CLASS}
          >
            <option value="">Todas</option>
            {maquinas.map(m => (
              <option key={m.id} value={m.id}>{m.nome}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-slate-400 mb-1.5">Ordem / Lote</label>
          <input
            type="text"
            value={ordemLote}
            onChange={e => setOrdemLote(e.target.value)}
            placeholder="Buscar por ordem ou lote"
            className={INPUT_CLASS}
          />
        </div>
      </div>
    </div>
  )
}
