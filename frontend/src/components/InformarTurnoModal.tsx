import { useEffect, useState } from 'react'
import { CheckCircle2 } from 'lucide-react'
import type { Maquina } from '@/api/maquinas'

interface Props {
  maquina: Maquina | null
  loading: boolean
  onClose: () => void
  onConfirm: (inicio: string, fim: string) => void
}

export function InformarTurnoModal({ maquina, loading, onClose, onConfirm }: Props) {
  const [inicio, setInicio] = useState('')
  const [fim, setFim]       = useState('')

  useEffect(() => {
    if (!maquina) {
      setInicio('')
      setFim('')
    }
  }, [maquina])

  if (!maquina) return null

  const fimInvalido = inicio !== '' && fim !== '' && fim <= inicio
  const valido       = inicio !== '' && fim !== '' && !fimInvalido

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={loading ? undefined : onClose}
      />

      <div className="relative z-10 w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
        <div className="px-6 py-5 space-y-4">

          <div>
            <h2 className="text-base font-semibold text-white leading-tight">{maquina.nome}</h2>
            <p className="text-sm text-slate-400 mt-1.5">
              Hoje não há turno cadastrado. Informe o horário de início e fim previsto para trabalhar nesta máquina.
            </p>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="block text-xs font-medium text-slate-400 mb-1.5">Início</span>
              <input
                type="time"
                value={inicio}
                onChange={e => setInicio(e.target.value)}
                disabled={loading}
                className="w-full h-12 px-3 text-base bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 disabled:opacity-50"
              />
            </label>
            <label className="block">
              <span className="block text-xs font-medium text-slate-400 mb-1.5">Fim</span>
              <input
                type="time"
                value={fim}
                onChange={e => setFim(e.target.value)}
                disabled={loading}
                className="w-full h-12 px-3 text-base bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 disabled:opacity-50"
              />
            </label>
          </div>

          {fimInvalido && (
            <p className="text-xs text-red-400">O horário de fim deve ser depois do início.</p>
          )}

          <div className="flex gap-3">
            <button
              type="button"
              onClick={onClose}
              disabled={loading}
              className="flex-1 h-12 text-sm font-medium text-slate-400 bg-white/5 hover:bg-white/10 disabled:opacity-40 rounded-lg transition-colors"
            >
              Cancelar
            </button>
            <button
              type="button"
              onClick={() => onConfirm(inicio, fim)}
              disabled={loading || !valido}
              className="flex-1 h-12 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              {loading
                ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                : <CheckCircle2 className="w-4 h-4" />
              }
              {loading ? 'Iniciando…' : 'Iniciar turno'}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
