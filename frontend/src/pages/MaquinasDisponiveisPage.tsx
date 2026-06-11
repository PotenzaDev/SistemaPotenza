import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { Loader2, MonitorSmartphone, ImageIcon, RotateCcw } from 'lucide-react'
import { getMaquinasDisponiveis, type Maquina } from '@/api/maquinas'
import { iniciarSessao, getSessaoAtiva } from '@/api/sessao'
import { ConfirmarMaquinaModal } from '@/components/ConfirmarMaquinaModal'
import { useAuth } from '@/hooks/useAuth'

export function MaquinasDisponiveisPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [maquinas, setMaquinas] = useState<Maquina[]>([])
  const [loading, setLoading]   = useState(true)
  const [error, setError]       = useState<string | null>(null)

  const [selecionada, setSelecionada]   = useState<Maquina | null>(null)
  const [confirmando, setConfirmando]   = useState(false)

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    setError(null)

    // Se há sessão ativa, redireciona direto para o apontamento em andamento
    getSessaoAtiva()
      .then(sessao => {
        if (controller.signal.aborted) return
        if (sessao) {
          navigate('/operario/apontamento', { replace: true })
          return
        }
        return getMaquinasDisponiveis(controller.signal).then(lista => {
          if (!controller.signal.aborted) setMaquinas(lista)
        })
      })
      .catch((err: unknown) => {
        if (!axios.isCancel(err) && !controller.signal.aborted) {
          setError('Não foi possível carregar as máquinas.')
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })

    return () => controller.abort()
  }, [navigate])

  async function handleConfirmar() {
    if (!selecionada) return
    setConfirmando(true)
    try {
      await iniciarSessao(selecionada.id)
      navigate('/operario/apontamento')
    } catch {
      setConfirmando(false)
    }
  }

  return (
    <div className="space-y-6 max-w-5xl mx-auto">

      {/* Saudação */}
      <div>
        <h1 className="text-xl font-semibold text-white">
          Olá, {user?.name?.split(' ')[0]}
        </h1>
        <p className="text-sm text-slate-400 mt-0.5">
          Selecione a máquina em que vai trabalhar hoje.
        </p>
      </div>

      {loading && (
        <div className="flex items-center justify-center gap-2 py-24 text-slate-400">
          <Loader2 className="w-5 h-5 animate-spin" />
          <span className="text-sm">Carregando máquinas…</span>
        </div>
      )}

      {error && (
        <div className="flex items-center justify-center py-24">
          <p className="text-sm text-red-400">{error}</p>
        </div>
      )}

      {!loading && !error && maquinas.length === 0 && (
        <div className="flex flex-col items-center justify-center py-24 gap-3 text-center">
          <div className="p-4 rounded-full bg-white/5">
            <MonitorSmartphone className="w-8 h-8 text-slate-600" />
          </div>
          <p className="text-sm font-medium text-slate-400">Nenhuma máquina disponível</p>
          <p className="text-xs text-slate-600">
            Não há máquinas ativas cadastradas para o seu setor.
          </p>
        </div>
      )}

      {!loading && !error && maquinas.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {maquinas.map(maquina => (
            <MaquinaCard key={maquina.id} maquina={maquina} onClick={() => setSelecionada(maquina)} />
          ))}
        </div>
      )}

      <ConfirmarMaquinaModal
        maquina={selecionada}
        loading={confirmando}
        pendencia={selecionada?.tem_pendencia ?? false}
        onClose={() => { if (!confirmando) setSelecionada(null) }}
        onConfirm={handleConfirmar}
      />
    </div>
  )
}

function MaquinaCard({ maquina, onClick }: { maquina: Maquina; onClick: () => void }) {
  const temPendencia = maquina.tem_pendencia === true

  return (
    <button
      type="button"
      onClick={onClick}
      className={[
        'group text-left rounded-xl overflow-hidden transition-all duration-200 focus:outline-none focus:ring-2',
        temPendencia
          ? 'bg-amber-500/5 border border-amber-500/30 hover:border-amber-400/60 hover:bg-amber-500/10 focus:ring-amber-400/50'
          : 'bg-[#0f1923] border border-white/5 hover:border-[#00aa84]/40 hover:bg-[#00aa84]/5 focus:ring-[#00aa84]/50',
      ].join(' ')}
    >
      {/* Foto */}
      <div className="relative w-full h-40 bg-white/[0.03] flex items-center justify-center overflow-hidden">
        {maquina.foto_url ? (
          <img
            src={maquina.foto_url}
            alt={maquina.nome}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
        ) : (
          <div className="flex flex-col items-center gap-2 text-slate-600">
            <ImageIcon className="w-8 h-8" />
          </div>
        )}
        {temPendencia && (
          <div className="absolute top-2 right-2 flex items-center gap-1 bg-amber-500/90 text-white text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-md">
            <RotateCcw className="w-3 h-3" />
            Retomar
          </div>
        )}
      </div>

      {/* Info */}
      <div className="p-4 space-y-2">
        <p className={[
          'font-semibold text-sm leading-tight transition-colors',
          temPendencia ? 'text-amber-300 group-hover:text-amber-200' : 'text-white group-hover:text-[#00aa84]',
        ].join(' ')}>
          {maquina.nome}
        </p>

        {maquina.codigo && (
          <p className="text-xs text-slate-500">{maquina.codigo}</p>
        )}

        {maquina.etapa_fluxo && (
          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-[#00aa84]/10 text-[#00aa84]">
            {maquina.etapa_fluxo.nome}
          </span>
        )}
      </div>
    </button>
  )
}
