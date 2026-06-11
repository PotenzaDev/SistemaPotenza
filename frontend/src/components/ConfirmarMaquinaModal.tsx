import { X, ImageIcon, CheckCircle2, RotateCcw } from 'lucide-react'
import type { Maquina } from '@/api/maquinas'

interface Props {
  maquina: Maquina | null
  loading: boolean
  pendencia?: boolean
  onClose: () => void
  onConfirm: () => void
}

export function ConfirmarMaquinaModal({ maquina, loading, pendencia = false, onClose, onConfirm }: Props) {
  if (!maquina) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={loading ? undefined : onClose}
      />

      <div className="relative z-10 w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl overflow-hidden">

        {/* Foto */}
        <div className="w-full h-40 bg-white/[0.03] flex items-center justify-center overflow-hidden">
          {maquina.foto_url ? (
            <img
              src={maquina.foto_url}
              alt={maquina.nome}
              className="w-full h-full object-cover"
            />
          ) : (
            <ImageIcon className="w-10 h-10 text-slate-700" />
          )}
        </div>

        <div className="px-6 py-5 space-y-4">

          {/* Info */}
          <div>
            <h2 className="text-base font-semibold text-white leading-tight">{maquina.nome}</h2>
            <div className="flex items-center gap-2 mt-1.5 flex-wrap">
              {maquina.codigo && (
                <span className="text-xs text-slate-500">{maquina.codigo}</span>
              )}
              {maquina.etapa_fluxo && (
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-[#00aa84]/10 text-[#00aa84]">
                  {maquina.etapa_fluxo.nome}
                </span>
              )}
            </div>
          </div>

          {pendencia ? (
            <div className="bg-amber-500/10 border border-amber-500/20 rounded-lg px-3 py-2.5">
              <p className="text-xs font-semibold text-amber-400">Apontamento pausado encontrado</p>
              <p className="text-xs text-amber-400/70 mt-0.5">
                Você tem um lote pausado nesta máquina. Ao confirmar, você retomará de onde parou.
              </p>
            </div>
          ) : (
            <p className="text-sm text-slate-400">
              Deseja iniciar uma sessão nesta máquina?
            </p>
          )}

          {/* Botões */}
          <div className="flex gap-3">
            <button
              type="button"
              onClick={onClose}
              disabled={loading}
              className="flex-1 py-2 text-sm font-medium text-slate-400 bg-white/5 hover:bg-white/10 disabled:opacity-40 rounded-lg transition-colors"
            >
              Cancelar
            </button>
            <button
              type="button"
              onClick={onConfirm}
              disabled={loading}
              className="flex-1 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              {loading
                ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                : pendencia
                  ? <RotateCcw className="w-4 h-4" />
                  : <CheckCircle2 className="w-4 h-4" />
              }
              {loading ? 'Iniciando…' : pendencia ? 'Retomar turno' : 'Confirmar'}
            </button>
          </div>
        </div>

        {!loading && (
          <button
            type="button"
            onClick={onClose}
            className="absolute top-3 right-3 p-1.5 rounded-lg text-white/60 hover:text-white hover:bg-white/10 transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>
    </div>
  )
}
