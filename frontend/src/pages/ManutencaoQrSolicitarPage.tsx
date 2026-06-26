import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { Wrench, Loader2, CheckCircle, AlertCircle } from 'lucide-react'
import axios from 'axios'
import { getMaquinaPublica, criarOrdemPublica, type MaquinaPublica, type PrioridadeQr } from '@/api/manutencao'

const PRIORIDADES: Array<{ value: PrioridadeQr; label: string; cor: string }> = [
  { value: 'baixa',   label: 'Baixa',   cor: 'border-slate-500 text-slate-300' },
  { value: 'normal',  label: 'Média',   cor: 'border-amber-500 text-amber-400' },
  { value: 'critica', label: 'Urgente', cor: 'border-red-500 text-red-400'     },
]

export function ManutencaoQrSolicitarPage() {
  const { maquinaId } = useParams<{ maquinaId: string }>()
  const id = Number(maquinaId)

  const [maquina, setMaquina]         = useState<MaquinaPublica | null>(null)
  const [loadingMaq, setLoadingMaq]   = useState(true)
  const [erroMaq, setErroMaq]         = useState(false)

  const [solicitante, setSolicitante] = useState('')
  const [motivo, setMotivo]           = useState('')
  const [prioridade, setPrioridade]   = useState<PrioridadeQr>('normal')

  const [enviando, setEnviando]       = useState(false)
  const [sucesso, setSucesso]         = useState(false)
  const [erroApi, setErroApi]         = useState<string | null>(null)

  useEffect(() => {
    if (!id) { setErroMaq(true); setLoadingMaq(false); return }
    const controller = new AbortController()
    getMaquinaPublica(id, controller.signal)
      .then(setMaquina)
      .catch((err: unknown) => {
        if (axios.isCancel(err)) return
        setErroMaq(true)
      })
      .finally(() => setLoadingMaq(false))
    return () => controller.abort()
  }, [id])

  function resetForm() {
    setSolicitante('')
    setMotivo('')
    setPrioridade('normal')
    setSucesso(false)
    setErroApi(null)
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!maquina || !solicitante.trim() || !motivo.trim()) return

    setEnviando(true)
    setErroApi(null)

    try {
      await criarOrdemPublica(maquina.id, {
        solicitante: solicitante.trim(),
        motivo: motivo.trim(),
        prioridade,
      })
      setSucesso(true)
    } catch (err) {
      setErroApi(
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message
          ?? 'Erro ao enviar. Tente novamente.',
      )
    } finally {
      setEnviando(false)
    }
  }

  const input = 'w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition text-base'

  return (
    <div className="min-h-screen bg-[#0b1219] flex flex-col items-center justify-center px-4 py-10">
      <div className="w-full max-w-md space-y-6">

        {/* Cabeçalho */}
        <div className="flex flex-col items-center gap-3 text-center">
          <div className="w-14 h-14 rounded-2xl bg-[#00aa84]/15 flex items-center justify-center">
            <Wrench className="w-7 h-7 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-white">Solicitar Manutenção</h1>
            {loadingMaq && <p className="text-sm text-slate-500 mt-1">Carregando…</p>}
            {!loadingMaq && maquina && (
              <p className="text-sm text-slate-400 mt-1">
                Máquina: <span className="text-white font-medium">{maquina.nome}</span>
              </p>
            )}
          </div>
        </div>

        {/* Erro ao carregar máquina */}
        {erroMaq && (
          <div className="flex items-start gap-3 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
            <AlertCircle className="w-5 h-5 text-red-400 mt-0.5 shrink-0" />
            <p className="text-sm text-red-400">Máquina não encontrada. Verifique o QR Code.</p>
          </div>
        )}

        {/* Tela de sucesso */}
        {sucesso && (
          <div className="bg-[#0f1923] border border-white/5 rounded-2xl p-8 flex flex-col items-center gap-4 text-center">
            <div className="w-16 h-16 rounded-full bg-emerald-500/15 flex items-center justify-center">
              <CheckCircle className="w-8 h-8 text-emerald-400" />
            </div>
            <div>
              <p className="text-lg font-semibold text-white">Solicitação enviada!</p>
              <p className="text-sm text-slate-400 mt-1">A equipe de manutenção foi notificada.</p>
            </div>
            <button
              onClick={resetForm}
              className="mt-2 px-6 py-2.5 text-sm font-medium bg-white/5 hover:bg-white/10 border border-white/10 text-white rounded-xl transition"
            >
              Enviar nova solicitação
            </button>
          </div>
        )}

        {/* Formulário */}
        {!sucesso && !erroMaq && (
          <form onSubmit={e => void handleSubmit(e)} className="space-y-4 bg-[#0f1923] border border-white/5 rounded-2xl p-6">

            {erroApi && (
              <div className="flex items-start gap-3 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
                <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
                <p className="text-sm text-red-400">{erroApi}</p>
              </div>
            )}

            {/* Solicitante */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium text-slate-300">Seu nome</label>
              <input
                type="text"
                required
                value={solicitante}
                onChange={e => setSolicitante(e.target.value)}
                placeholder="Nome ou matrícula"
                className={input}
              />
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
                className={[input, 'resize-none'].join(' ')}
              />
            </div>

            {/* Prioridade */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium text-slate-300">Prioridade</label>
              <div className="grid grid-cols-3 gap-2">
                {PRIORIDADES.map(p => (
                  <button
                    key={p.value}
                    type="button"
                    onClick={() => setPrioridade(p.value)}
                    className={[
                      'py-2.5 rounded-xl text-sm font-medium border transition',
                      prioridade === p.value
                        ? p.cor + ' bg-white/5'
                        : 'border-white/10 text-slate-500 hover:text-slate-300',
                    ].join(' ')}
                  >
                    {p.label}
                  </button>
                ))}
              </div>
            </div>

            {/* Botão enviar */}
            <button
              type="submit"
              disabled={enviando || loadingMaq || !solicitante.trim() || !motivo.trim()}
              className="h-14 w-full bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed text-white text-base font-semibold rounded-xl transition-colors flex items-center justify-center gap-2 mt-2"
            >
              {enviando
                ? <><Loader2 className="w-5 h-5 animate-spin" /> Enviando…</>
                : 'Enviar Solicitação'
              }
            </button>
          </form>
        )}
      </div>
    </div>
  )
}
