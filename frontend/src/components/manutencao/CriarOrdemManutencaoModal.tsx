import { useEffect, useState } from 'react'
import axios from 'axios'
import { X, Wrench, Loader2 } from 'lucide-react'
import { criarOrdemAdmin, type OrdemManutencao, type Prioridade } from '@/api/manutencao'
import { getMaquinas, type Maquina } from '@/api/maquinas'
import { useAuth } from '@/hooks/useAuth'

export interface CriarOrdemManutencaoModalProps {
  onClose: () => void
  onCreated: (ordem: OrdemManutencao) => void
}

const PRIORIDADES: Array<{ value: Prioridade; label: string }> = [
  { value: 'baixa', label: 'Baixa' },
  { value: 'normal', label: 'Normal' },
  { value: 'alta', label: 'Alta' },
  { value: 'critica', label: 'Crítica' },
]

export function CriarOrdemManutencaoModal({ onClose, onCreated }: CriarOrdemManutencaoModalProps) {
  const { user } = useAuth()

  const [maquinas, setMaquinas] = useState<Maquina[]>([])
  const [loadingMaquinas, setLoadingMaquinas] = useState(true)

  const [maquinaId, setMaquinaId] = useState('')
  const [solicitante, setSolicitante] = useState(user?.name ?? '')
  const [motivo, setMotivo] = useState('')
  const [prioridade, setPrioridade] = useState<Prioridade>('normal')

  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    setLoadingMaquinas(true)
    getMaquinas(controller.signal)
      .then(setMaquinas)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar as máquinas.')
      })
      .finally(() => setLoadingMaquinas(false))
    return () => controller.abort()
  }, [])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)

    if (!maquinaId)             { setError('Selecione uma máquina.'); return }
    if (!solicitante.trim())    { setError('Informe o solicitante.'); return }
    if (!motivo.trim())         { setError('Informe o motivo.'); return }

    setSaving(true)
    try {
      const ordem = await criarOrdemAdmin({
        maquina_id: Number(maquinaId),
        solicitante: solicitante.trim(),
        motivo: motivo.trim(),
        prioridade,
      })
      onCreated(ordem)
      onClose()
    } catch (err: unknown) {
      if (axios.isAxiosError(err) && err.response?.data?.errors) {
        const msgs = Object.values(err.response.data.errors as Record<string, string[]>)
          .flat()
          .join(' ')
        setError(msgs)
      } else {
        setError('Não foi possível criar a OS.')
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

      <div className="relative z-10 w-full max-w-md bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl">

        <div className="flex items-center justify-between px-6 py-4 border-b border-white/5">
          <div className="flex items-center gap-2">
            <Wrench className="w-4 h-4 text-[#00aa84]" />
            <h2 className="text-base font-semibold text-white">Nova OS</h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-4">

          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Máquina <span className="text-red-400">*</span>
            </label>
            <select
              value={maquinaId}
              onChange={e => setMaquinaId(e.target.value)}
              disabled={loadingMaquinas}
              className="w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors disabled:opacity-50"
            >
              <option value="">{loadingMaquinas ? 'Carregando…' : 'Selecione uma máquina'}</option>
              {maquinas.map(m => (
                <option key={m.id} value={String(m.id)}>{m.nome}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Solicitante <span className="text-red-400">*</span>
            </label>
            <input
              value={solicitante}
              onChange={e => setSolicitante(e.target.value)}
              placeholder="Nome do solicitante"
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Motivo <span className="text-red-400">*</span>
            </label>
            <textarea
              value={motivo}
              onChange={e => setMotivo(e.target.value)}
              rows={3}
              placeholder="Descreva o problema ou serviço necessário"
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors resize-none"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">Prioridade</label>
            <select
              value={prioridade}
              onChange={e => setPrioridade(e.target.value as Prioridade)}
              className="w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors"
            >
              {PRIORIDADES.map(p => (
                <option key={p.value} value={p.value}>{p.label}</option>
              ))}
            </select>
          </div>

          {error && (
            <p className="text-xs text-red-400 bg-red-400/10 border border-red-400/20 rounded-lg px-3 py-2">
              {error}
            </p>
          )}

          <div className="flex gap-3 pt-1">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-2 text-sm font-medium text-slate-400 bg-white/5 hover:bg-white/10 rounded-lg transition-colors"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={saving}
              className="flex-1 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              {saving && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
              {saving ? 'Criando…' : 'Criar OS'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
