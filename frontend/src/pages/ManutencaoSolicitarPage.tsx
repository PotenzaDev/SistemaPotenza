import { useEffect, useState } from 'react'
import { Wrench, Loader2, CheckCircle, AlertCircle } from 'lucide-react'
import { getMaquinas, type Maquina } from '@/api/maquinas'
import { criarOrdem, type Prioridade } from '@/api/manutencao'

const PRIORIDADES: Array<{ value: Prioridade; label: string }> = [
  { value: 'baixa', label: 'Baixa' },
  { value: 'normal', label: 'Normal' },
  { value: 'alta', label: 'Alta' },
  { value: 'critica', label: 'Crítica' },
]

export function ManutencaoSolicitarPage() {
  const [maquinas, setMaquinas] = useState<Maquina[]>([])
  const [loadingMaquinas, setLoadingMaquinas] = useState(true)

  const [maquinaId, setMaquinaId]     = useState<string>('')
  const [motivo, setMotivo]           = useState<string>('')
  const [prioridade, setPrioridade]   = useState<Prioridade>('normal')
  const [solicitante, setSolicitante] = useState<string>('')

  const [enviando, setEnviando]       = useState(false)
  const [sucesso, setSucesso]         = useState(false)
  const [erroApi, setErroApi]         = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    getMaquinas(controller.signal)
      .then(data => { setMaquinas(data); setLoadingMaquinas(false) })
      .catch(err => {
        if ((err as { name?: string }).name === 'CanceledError' || (err as { name?: string }).name === 'AbortError') return
        setLoadingMaquinas(false)
      })
    return () => { controller.abort() }
  }, [])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!maquinaId || !motivo.trim() || !solicitante.trim()) return

    setEnviando(true)
    setSucesso(false)
    setErroApi(null)

    try {
      await criarOrdem({
        maquina_id: Number(maquinaId),
        solicitante: solicitante.trim(),
        motivo: motivo.trim(),
        prioridade,
      })
      setSucesso(true)
      setMaquinaId('')
      setMotivo('')
      setPrioridade('normal')
      setSolicitante('')
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setEnviando(false)
    }
  }

  const inputBase = 'w-full px-4 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition'

  return (
    <div className="max-w-xl mx-auto space-y-6 py-4">
      {/* Cabeçalho */}
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 rounded-xl bg-[#00aa84]/15 flex items-center justify-center shrink-0">
          <Wrench className="w-5 h-5 text-[#00aa84]" />
        </div>
        <h1 className="text-xl font-bold text-white">Solicitar Manutenção</h1>
      </div>

      {/* Banner sucesso */}
      {sucesso && (
        <div className="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-4 py-3">
          <CheckCircle className="w-5 h-5 text-emerald-400 mt-0.5 shrink-0" />
          <p className="text-sm text-emerald-400 font-medium">Solicitação enviada com sucesso!</p>
        </div>
      )}

      {/* Banner erro */}
      {erroApi && (
        <div className="flex items-start gap-3 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
          <AlertCircle className="w-5 h-5 text-red-400 mt-0.5 shrink-0" />
          <p className="text-sm text-red-400">{erroApi}</p>
        </div>
      )}

      {/* Formulário */}
      <form onSubmit={e => void handleSubmit(e)} className="space-y-4">
        {/* Máquina */}
        <div className="space-y-1.5">
          <label className="text-sm font-medium text-slate-300">Máquina</label>
          <select
            required
            value={maquinaId}
            onChange={e => setMaquinaId(e.target.value)}
            disabled={loadingMaquinas}
            className={[inputBase, 'h-12 appearance-none cursor-pointer disabled:opacity-50'].join(' ')}
          >
            <option value="" disabled className="bg-[#0f1923]">
              {loadingMaquinas ? 'Carregando…' : 'Selecione a máquina'}
            </option>
            {maquinas.map(m => (
              <option key={m.id} value={String(m.id)} className="bg-[#0f1923]">
                {m.nome}
              </option>
            ))}
          </select>
        </div>

        {/* Motivo */}
        <div className="space-y-1.5">
          <label className="text-sm font-medium text-slate-300">Descrição do problema</label>
          <textarea
            required
            value={motivo}
            onChange={e => setMotivo(e.target.value)}
            placeholder="Descreva o problema detalhadamente…"
            rows={4}
            className={[inputBase, 'py-3 resize-none'].join(' ')}
          />
        </div>

        {/* Prioridade */}
        <div className="space-y-1.5">
          <label className="text-sm font-medium text-slate-300">Prioridade</label>
          <select
            value={prioridade}
            onChange={e => setPrioridade(e.target.value as Prioridade)}
            className={[inputBase, 'h-12 appearance-none cursor-pointer'].join(' ')}
          >
            {PRIORIDADES.map(p => (
              <option key={p.value} value={p.value} className="bg-[#0f1923]">
                {p.label}
              </option>
            ))}
          </select>
        </div>

        {/* Solicitante */}
        <div className="space-y-1.5">
          <label className="text-sm font-medium text-slate-300">Seu nome ou matrícula</label>
          <input
            type="text"
            required
            value={solicitante}
            onChange={e => setSolicitante(e.target.value)}
            placeholder="Seu nome ou matrícula"
            className={[inputBase, 'h-12'].join(' ')}
          />
        </div>

        {/* Botão enviar */}
        <button
          type="submit"
          disabled={enviando || !maquinaId || !motivo.trim() || !solicitante.trim()}
          className="h-14 w-full bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed text-white text-lg font-semibold rounded-xl transition-colors flex items-center justify-center gap-2"
        >
          {enviando
            ? <><Loader2 className="w-5 h-5 animate-spin" /> Enviando…</>
            : 'Enviar Solicitação'
          }
        </button>
      </form>
    </div>
  )
}

function apiMsg(err: unknown): string {
  return (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    ?? 'Erro ao enviar solicitação. Tente novamente.'
}
