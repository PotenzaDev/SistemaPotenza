import { useCallback, useEffect, useState } from 'react'
import { Bell, RefreshCw, Cpu, Clock, CheckCircle2 } from 'lucide-react'
import { getChamadasSuporte, visualizarChamada, type ChamadaSuporte } from '@/api/suporte'

type Filtro = 'pendentes' | 'hoje'

function tempoRelativo(iso: string): string {
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60) return 'agora mesmo'
  if (diff < 3600) return `há ${Math.floor(diff / 60)} min`
  return `há ${Math.floor(diff / 3600)}h`
}

export function ChamadasSuportePage() {
  const [chamadas, setChamadas]       = useState<ChamadaSuporte[]>([])
  const [loading, setLoading]         = useState(true)
  const [filtro, setFiltro]           = useState<Filtro>('pendentes')
  const [lastUpdate, setLastUpdate]   = useState<Date | null>(null)
  const [dispensando, setDispensando] = useState<number | null>(null)

  const load = useCallback(async () => {
    const result = await getChamadasSuporte()
    setChamadas(result)
    setLastUpdate(new Date())
    setLoading(false)
  }, [])

  useEffect(() => {
    load()
    const id = setInterval(load, 15_000)
    return () => clearInterval(id)
  }, [load])

  async function handleDispensar(id: number) {
    setDispensando(id)
    try {
      await visualizarChamada(id)
      setChamadas(prev => prev.filter(c => c.id !== id))
    } finally {
      setDispensando(null)
    }
  }

  const hoje = new Date().toDateString()
  const chamadasHoje = chamadas.filter(c => new Date(c.criado_em).toDateString() === hoje)
  const listaAtual   = filtro === 'pendentes' ? chamadas : chamadasHoje

  const PILLS: { label: string; value: Filtro; count: number }[] = [
    { label: 'Pendentes',     value: 'pendentes', count: chamadas.length },
    { label: 'Todas de hoje', value: 'hoje',      count: chamadasHoje.length },
  ]

  return (
    <div className="space-y-6">

      {/* Cabeçalho */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-orange-500/10">
            <Bell className="w-5 h-5 text-orange-400" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Chamadas de Suporte</h1>
            <p className="text-sm text-slate-400">Solicitações enviadas pelos operadores</p>
          </div>
        </div>
        {lastUpdate && (
          <span className="text-xs text-slate-500">
            Atualizado às {lastUpdate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
          </span>
        )}
      </div>

      {/* Pills de filtro */}
      <div className="flex gap-2">
        {PILLS.map(pill => (
          <button
            key={pill.value}
            type="button"
            onClick={() => setFiltro(pill.value)}
            className={[
              'flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
              filtro === pill.value
                ? 'bg-[#00aa84]/15 text-[#00aa84]'
                : 'text-slate-400 hover:bg-white/5 hover:text-white',
            ].join(' ')}
          >
            {pill.label}
            {pill.count > 0 && (
              <span className={[
                'inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold',
                filtro === pill.value
                  ? 'bg-[#00aa84]/30 text-[#00aa84]'
                  : 'bg-white/10 text-slate-300',
              ].join(' ')}>
                {pill.count}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Conteúdo */}
      {loading ? (
        <div className="flex items-center justify-center gap-2 py-20 text-slate-400">
          <RefreshCw className="w-4 h-4 animate-spin" />
          <span className="text-sm">Carregando…</span>
        </div>
      ) : listaAtual.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-3 py-20 text-center">
          <div className="p-4 rounded-full bg-white/5">
            <CheckCircle2 className="w-8 h-8 text-slate-600" />
          </div>
          <p className="text-slate-400 text-sm font-medium">
            {filtro === 'pendentes' ? 'Nenhuma chamada pendente' : 'Nenhuma chamada hoje'}
          </p>
          <p className="text-slate-600 text-xs">
            {filtro === 'pendentes'
              ? 'Tudo em ordem no chão de fábrica.'
              : 'Sem solicitações registradas hoje.'}
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {listaAtual.map(c => (
            <div
              key={c.id}
              className="flex items-center gap-4 bg-[#0f1923] border border-white/5 rounded-xl px-5 py-4"
            >
              <div className="p-2 rounded-lg bg-orange-500/10 shrink-0">
                <Bell className="w-4 h-4 text-orange-400" />
              </div>

              <div className="flex-1 min-w-0 space-y-1">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="flex items-center gap-1.5 text-sm font-semibold text-white">
                    <Cpu className="w-3.5 h-3.5 text-slate-500 shrink-0" />
                    {c.maquina.nome}
                  </span>
                  <span className="text-slate-600">·</span>
                  <span className="text-sm text-slate-400">{c.operario.nome}</span>
                </div>
                <div className="flex items-center gap-1.5 text-xs text-slate-500">
                  <Clock className="w-3 h-3" />
                  {new Date(c.criado_em).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                  <span className="text-slate-600">·</span>
                  {tempoRelativo(c.criado_em)}
                </div>
              </div>

              <button
                type="button"
                onClick={() => handleDispensar(c.id)}
                disabled={dispensando === c.id}
                className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 bg-white/5 hover:bg-white/10 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                {dispensando === c.id
                  ? <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                  : <CheckCircle2 className="w-3.5 h-3.5" />}
                Dispensar
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
