import { X, RotateCcw, Play, Loader2 } from 'lucide-react'
import type { Maquina } from '@/api/maquinas'
import type { SessaoPausada } from '@/api/sessao'
import { fmtHoraDate } from '@/lib/apontamentoFormat'

interface EscolherSessaoModalProps {
  maquina: Maquina | null
  sessoesPausadas: SessaoPausada[]
  carregando: boolean
  retomandoId: number | null
  iniciandoNova: boolean
  onClose: () => void
  onRetomar: (sessaoId: number) => void
  onIniciarNova: () => void
}

export function EscolherSessaoModal({
  maquina, sessoesPausadas, carregando, retomandoId, iniciandoNova,
  onClose, onRetomar, onIniciarNova,
}: EscolherSessaoModalProps) {
  if (!maquina) return null

  const desabilitado = carregando || retomandoId !== null || iniciandoNova

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={desabilitado ? undefined : onClose}
      />

      <div className="relative z-10 w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
        <div className="px-6 py-5 space-y-4">
          <div>
            <h2 className="text-base font-semibold text-white leading-tight">{maquina.nome}</h2>
            <p className="text-sm text-slate-400 mt-1">
              {sessoesPausadas.length === 1
                ? 'Você tem uma sessão pausada nesta máquina.'
                : `Você tem ${sessoesPausadas.length} sessões pausadas nesta máquina.`}
              {' '}Retome uma delas ou inicie uma sessão nova.
            </p>
          </div>

          {carregando ? (
            <div className="flex items-center justify-center py-6 text-slate-400">
              <Loader2 className="w-5 h-5 animate-spin" />
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {sessoesPausadas.map(sessao => (
                <button
                  key={sessao.id}
                  type="button"
                  onClick={() => onRetomar(sessao.id)}
                  disabled={desabilitado}
                  className="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-amber-500/5 hover:bg-amber-500/10 border border-amber-500/20 text-left transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-amber-300 truncate">
                      {sessao.ordem_lote ? `Lote ${sessao.ordem_lote.replace(/^0+/, '')}` : 'Sem lote iniciado'}
                    </p>
                    <p className="text-xs text-amber-400/70 truncate">
                      {sessao.desc_peca ?? 'Pausada'}
                      {sessao.pausada_em && ` · pausada às ${fmtHoraDate(new Date(sessao.pausada_em))}`}
                    </p>
                  </div>
                  {retomandoId === sessao.id
                    ? <Loader2 className="w-4 h-4 animate-spin text-amber-400 shrink-0" />
                    : <RotateCcw className="w-4 h-4 text-amber-400 shrink-0" />}
                </button>
              ))}
            </div>
          )}

          <div className="border-t border-white/5 pt-4">
            <button
              type="button"
              onClick={onIniciarNova}
              disabled={desabilitado}
              className="w-full py-2.5 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              {iniciandoNova ? <Loader2 className="w-4 h-4 animate-spin" /> : <Play className="w-4 h-4" />}
              Iniciar sessão nova
            </button>
          </div>
        </div>

        {!desabilitado && (
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
