import { useEffect, useMemo, useState } from 'react'
import { Wrench, Loader2, AlertCircle, X, CalendarDays, ArrowUpDown, Headphones, CheckCircle2, Plus } from 'lucide-react'
import {
  getOrdensManutencao,
  type OrdemManutencao,
  type StatusOrdem,
  type Prioridade,
} from '@/api/manutencao'
import { chamarSuporteManutencao } from '@/api/suporte'
import { OrdemManutencaoModal } from '@/components/manutencao/OrdemManutencaoModal'
import { CriarOrdemManutencaoModal } from '@/components/manutencao/CriarOrdemManutencaoModal'

const PRIORIDADE_ORDER: Prioridade[] = ['critica', 'alta', 'normal', 'baixa']

const PRIORIDADE_LABEL: Record<Prioridade, string> = {
  critica: 'Crítica',
  alta: 'Alta',
  normal: 'Normal',
  baixa: 'Baixa',
}

const PRIORIDADE_COLOR: Record<Prioridade, string> = {
  critica: 'text-red-400',
  alta: 'text-orange-400',
  normal: 'text-blue-400',
  baixa: 'text-slate-400',
}

const PRIORIDADE_BADGE: Record<Prioridade, string> = {
  critica: 'bg-red-500/15 text-red-400',
  alta: 'bg-orange-500/15 text-orange-400',
  normal: 'bg-blue-500/15 text-blue-400',
  baixa: 'bg-slate-700/50 text-slate-400',
}

const STATUS_BADGE: Record<StatusOrdem, string> = {
  aberta: 'bg-yellow-500/15 text-yellow-400',
  em_atendimento: 'bg-blue-500/15 text-blue-400',
  pausada: 'bg-orange-500/15 text-orange-400',
  concluida: 'bg-emerald-500/15 text-emerald-400',
  cancelada: 'bg-slate-700/50 text-slate-500',
}

const STATUS_LABEL: Record<StatusOrdem, string> = {
  aberta: 'Aberta',
  em_atendimento: 'Em Atendimento',
  pausada: 'Pausada',
  concluida: 'Concluída',
  cancelada: 'Cancelada',
}

interface PillDef {
  label: string
  value: StatusOrdem | null
}

const PILLS: PillDef[] = [
  { label: 'Ativas', value: null },
  { label: 'Abertas', value: 'aberta' },
  { label: 'Em Atendimento', value: 'em_atendimento' },
  { label: 'Pausadas', value: 'pausada' },
  { label: 'Concluídas', value: 'concluida' },
  { label: 'Canceladas', value: 'cancelada' },
]

export function ManutencaoPainelPage() {
  const [ordens, setOrdens]                     = useState<OrdemManutencao[]>([])
  const [loading, setLoading]                   = useState(true)
  const [erroApi, setErroApi]                   = useState<string | null>(null)
  const [ordemSelecionada, setOrdemSelecionada] = useState<OrdemManutencao | null>(null)
  const [modalCriarAberto, setModalCriarAberto] = useState(false)

  const [suporteStatus, setSuporteStatus] = useState<'idle' | 'loading' | 'ok'>('idle')

  const [filtroStatus, setFiltroStatus]   = useState<StatusOrdem | null>(null)
  const [filtroData, setFiltroData]       = useState<string>('')
  const [filtroMaquina, setFiltroMaquina] = useState<string>('')
  const [filtroSetor, setFiltroSetor]     = useState<string>('')
  const [ordenacao, setOrdenacao]         = useState<'data' | 'prioridade'>('data')

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    setErroApi(null)

    const params: { status?: StatusOrdem; data?: string } = {}
    if (filtroStatus) params.status = filtroStatus
    if (filtroData) params.data = filtroData

    getOrdensManutencao(params, controller.signal)
      .then(data => { setOrdens(data); setLoading(false) })
      .catch(err => {
        if ((err as { name?: string }).name === 'CanceledError' || (err as { name?: string }).name === 'AbortError') return
        setErroApi(apiMsg(err))
        setLoading(false)
      })

    return () => { controller.abort() }
  }, [filtroStatus, filtroData])

  // Setores únicos derivados das ordens carregadas
  const setoresDisponiveis = useMemo(() => {
    const nomes = ordens.map(o => o.maquina.etapa_fluxo?.nome ?? 'Sem setor')
    return [...new Set(nomes)].sort((a, b) => a.localeCompare(b, 'pt-BR'))
  }, [ordens])

  // Máquinas únicas para datalist
  const maquinasDisponiveis = useMemo(() => {
    const nomes = ordens.map(o => o.maquina.nome)
    return [...new Set(nomes)].sort((a, b) => a.localeCompare(b, 'pt-BR'))
  }, [ordens])

  // Ordens com todos os filtros aplicados
  const ordensBase = useMemo(() => {
    const STATUS_ATIVOS: StatusOrdem[] = ['aberta', 'em_atendimento', 'pausada']
    let lista = filtroStatus === null
      ? ordens.filter(o => STATUS_ATIVOS.includes(o.status))
      : ordens

    if (filtroMaquina.trim()) {
      const busca = filtroMaquina.trim().toLowerCase()
      lista = lista.filter(o => o.maquina.nome.toLowerCase().includes(busca))
    }

    if (filtroSetor) {
      lista = lista.filter(o => (o.maquina.etapa_fluxo?.nome ?? 'Sem setor') === filtroSetor)
    }

    return lista
  }, [ordens, filtroStatus, filtroMaquina, filtroSetor])

  // Vista plana por data — mais recente primeiro (concluído_em ou solicitado_em)
  const ordensPlanasPorData = useMemo(() => {
    return [...ordensBase].sort((a, b) => {
      const ta = a.concluido_em ?? a.solicitado_em
      const tb = b.concluido_em ?? b.solicitado_em
      return new Date(tb).getTime() - new Date(ta).getTime()
    })
  }, [ordensBase])

  // Vista agrupada por prioridade → setor
  const agrupado = useMemo(() => {
    const result: Array<{
      prioridade: Prioridade
      setores: Array<{ nome: string; ordens: OrdemManutencao[] }>
    }> = []

    for (const prioridade of PRIORIDADE_ORDER) {
      const destaP = ordensBase.filter(o => o.prioridade === prioridade)
      if (destaP.length === 0) continue

      const setorMap = new Map<string, OrdemManutencao[]>()
      for (const o of destaP) {
        const nome = o.maquina.etapa_fluxo?.nome ?? 'Sem setor'
        const lista = setorMap.get(nome) ?? []
        lista.push(o)
        setorMap.set(nome, lista)
      }

      result.push({
        prioridade,
        setores: Array.from(setorMap.entries()).map(([nome, ordens]) => ({ nome, ordens })),
      })
    }

    return result
  }, [ordensBase])

  const mostrarFlatData = filtroStatus === 'concluida' && ordenacao === 'data'
  const temFiltrosAtivos = filtroStatus !== null || filtroData !== '' || filtroMaquina !== '' || filtroSetor !== ''

  async function handleChamarSuporte() {
    if (suporteStatus === 'loading') return
    setSuporteStatus('loading')
    try {
      await chamarSuporteManutencao()
      setSuporteStatus('ok')
      setTimeout(() => setSuporteStatus('idle'), 3000)
    } catch {
      setSuporteStatus('idle')
    }
  }

  function handleLimpar() {
    setFiltroStatus(null)
    setFiltroData('')
    setFiltroMaquina('')
    setFiltroSetor('')
  }

  function handleUpdate(updated: OrdemManutencao) {
    setOrdens(prev => prev.map(o => o.id === updated.id ? updated : o))
    setOrdemSelecionada(prev => prev?.id === updated.id ? updated : prev)
  }

  function handleCreated(nova: OrdemManutencao) {
    setOrdens(prev => [nova, ...prev])
  }

  return (
    <div className="space-y-5">
      {/* Cabeçalho */}
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-[#00aa84]/15 flex items-center justify-center shrink-0">
            <Wrench className="w-5 h-5 text-[#00aa84]" />
          </div>
          <h1 className="text-xl font-bold text-white">Painel de Manutenção</h1>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => setModalCriarAberto(true)}
            className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-[#00aa84] text-white hover:bg-[#00aa84]/90 transition-all"
          >
            <Plus className="w-4 h-4" />
            Nova OS
          </button>

          <button
            type="button"
            onClick={handleChamarSuporte}
            disabled={suporteStatus === 'loading'}
            className={[
              'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium border transition-all',
              suporteStatus === 'ok'
                ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
                : 'bg-orange-500/10 text-orange-400 border-orange-500/20 hover:bg-orange-500/20 disabled:opacity-50 disabled:cursor-not-allowed',
            ].join(' ')}
          >
            {suporteStatus === 'loading' && <Loader2 className="w-4 h-4 animate-spin" />}
            {suporteStatus === 'ok'      && <CheckCircle2 className="w-4 h-4" />}
            {suporteStatus === 'idle'    && <Headphones className="w-4 h-4" />}
            {suporteStatus === 'ok' ? 'Suporte avisado!' : 'Chamar Suporte'}
          </button>
        </div>
      </div>

      {/* Filtros */}
      <div className="space-y-2.5">

        {/* Linha 1: Pills de status */}
        <div className="flex flex-wrap gap-1.5">
          {PILLS.map(pill => {
            const ativo = filtroStatus === pill.value
            return (
              <button
                key={pill.label}
                type="button"
                onClick={() => setFiltroStatus(pill.value)}
                className={[
                  'px-3 py-1.5 rounded-full text-xs font-medium border transition-colors',
                  ativo
                    ? 'bg-[#00aa84]/20 text-[#00aa84] border-[#00aa84]/40'
                    : 'bg-white/5 text-slate-400 border-white/10 hover:bg-white/10',
                ].join(' ')}
              >
                {pill.label}
              </button>
            )
          })}
        </div>

        {/* Linha 2: Máquina + Setor + Data + Limpar */}
        <div className="flex flex-wrap items-center gap-2">

          {/* Máquina (com datalist) */}
          <div className="relative">
            <input
              type="text"
              list="maquinas-list"
              value={filtroMaquina}
              onChange={e => setFiltroMaquina(e.target.value)}
              placeholder="Filtrar máquina…"
              className="w-44 px-3 py-1.5 pr-7 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
            />
            <datalist id="maquinas-list">
              {maquinasDisponiveis.map(nome => <option key={nome} value={nome} />)}
            </datalist>
            {filtroMaquina && (
              <button
                type="button"
                onClick={() => setFiltroMaquina('')}
                className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
              >
                <X className="w-3 h-3" />
              </button>
            )}
          </div>

          {/* Setor */}
          <select
            value={filtroSetor}
            onChange={e => setFiltroSetor(e.target.value)}
            className="w-44 px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
          >
            <option value="">Todos os setores</option>
            {setoresDisponiveis.map(nome => (
              <option key={nome} value={nome}>{nome}</option>
            ))}
          </select>

          {/* Data */}
          <input
            type="date"
            value={filtroData}
            onChange={e => setFiltroData(e.target.value)}
            className="px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
          />

          {/* Limpar */}
          {temFiltrosAtivos && (
            <button
              type="button"
              onClick={handleLimpar}
              className="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors"
            >
              <X className="w-3 h-3" />
              Limpar
            </button>
          )}
        </div>

        {/* Linha 3: Ordenação — só visível em Concluídas */}
        {filtroStatus === 'concluida' && (
          <div className="flex items-center gap-2">
            <span className="text-xs text-slate-500">Ordenar por:</span>
            <div className="flex rounded-lg border border-white/10 overflow-hidden">
              <button
                type="button"
                onClick={() => setOrdenacao('data')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium transition-colors ${
                  ordenacao === 'data'
                    ? 'bg-[#00aa84]/20 text-[#00aa84]'
                    : 'bg-white/5 text-slate-400 hover:bg-white/10'
                }`}
              >
                <CalendarDays className="w-3 h-3" />
                Data
              </button>
              <button
                type="button"
                onClick={() => setOrdenacao('prioridade')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border-l border-white/10 transition-colors ${
                  ordenacao === 'prioridade'
                    ? 'bg-[#00aa84]/20 text-[#00aa84]'
                    : 'bg-white/5 text-slate-400 hover:bg-white/10'
                }`}
              >
                <ArrowUpDown className="w-3 h-3" />
                Prioridade
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Erro */}
      {erroApi && (
        <div className="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
          <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
          <p className="text-xs text-red-400">{erroApi}</p>
        </div>
      )}

      {/* Loading */}
      {loading && (
        <div className="flex items-center justify-center py-20 gap-2 text-slate-400">
          <Loader2 className="w-5 h-5 animate-spin" />
          <span className="text-sm">Carregando ordens…</span>
        </div>
      )}

      {/* Empty */}
      {!loading && !erroApi && ordensBase.length === 0 && (
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <Wrench className="w-10 h-10 text-slate-700 mb-3" />
          <p className="text-sm text-slate-500">Nenhuma ordem encontrada.</p>
          <p className="text-xs text-slate-600 mt-1">Tente ajustar os filtros.</p>
        </div>
      )}

      {/* ── Vista plana: concluídas por data ──────────────────────────────── */}
      {!loading && !erroApi && mostrarFlatData && ordensBase.length > 0 && (
        <div className="space-y-2">
          {ordensPlanasPorData.map(ordem => (
            <OrdemCard key={ordem.id} ordem={ordem} onClick={() => setOrdemSelecionada(ordem)} />
          ))}
        </div>
      )}

      {/* ── Vista agrupada: por prioridade → setor ───────────────────────── */}
      {!loading && !erroApi && !mostrarFlatData && agrupado.map(grupo => (
        <div key={grupo.prioridade} className="space-y-4">
          <div className="flex items-center gap-2">
            <span className={['text-sm font-bold uppercase tracking-wider', PRIORIDADE_COLOR[grupo.prioridade]].join(' ')}>
              {PRIORIDADE_LABEL[grupo.prioridade]}
            </span>
            <div className="flex-1 h-px bg-white/5" />
          </div>

          {grupo.setores.map(setor => (
            <div key={setor.nome} className="space-y-2">
              <p className="text-xs text-slate-500 font-medium pl-1">{setor.nome}</p>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {setor.ordens.map(ordem => (
                  <OrdemCard key={ordem.id} ordem={ordem} onClick={() => setOrdemSelecionada(ordem)} />
                ))}
              </div>
            </div>
          ))}
        </div>
      ))}

      {/* Modal de detalhe/edição */}
      {ordemSelecionada !== null && (
        <OrdemManutencaoModal
          ordem={ordemSelecionada}
          onClose={() => setOrdemSelecionada(null)}
          onUpdate={handleUpdate}
        />
      )}

      {/* Modal de criação */}
      {modalCriarAberto && (
        <CriarOrdemManutencaoModal
          onClose={() => setModalCriarAberto(false)}
          onCreated={handleCreated}
        />
      )}
    </div>
  )
}

// ── Card reutilizável de OS ────────────────────────────────────────────────────

interface OrdemCardProps {
  ordem: OrdemManutencao
  onClick: () => void
}

function OrdemCard({ ordem, onClick }: OrdemCardProps) {
  const dataExibida = ordem.concluido_em ?? ordem.solicitado_em
  const labelData   = ordem.concluido_em ? 'Concluído' : 'Solicitado'

  return (
    <button
      type="button"
      onClick={onClick}
      className="w-full text-left bg-[#0f1923] border border-white/5 rounded-xl p-4 hover:border-[#00aa84]/40 cursor-pointer transition-colors space-y-2"
    >
      {/* OS + máquina */}
      <div className="flex items-baseline justify-between gap-2 min-w-0">
        <span className="text-xs text-slate-500 shrink-0">OS #{ordem.id}</span>
        <span className="font-semibold text-white text-sm truncate">{ordem.maquina.nome}</span>
      </div>

      {/* Badges */}
      <div className="flex items-center gap-1.5 flex-wrap">
        <span className={['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', PRIORIDADE_BADGE[ordem.prioridade]].join(' ')}>
          {PRIORIDADE_LABEL[ordem.prioridade]}
        </span>
        <span className={['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', STATUS_BADGE[ordem.status]].join(' ')}>
          {STATUS_LABEL[ordem.status]}
        </span>
      </div>

      {/* Setor */}
      {ordem.maquina.etapa_fluxo && (
        <p className="text-xs text-slate-500">{ordem.maquina.etapa_fluxo.nome}</p>
      )}

      {/* Motivo */}
      <p className="text-sm text-slate-300 line-clamp-2">{ordem.motivo}</p>

      {/* Data */}
      <p className="text-xs text-slate-500">
        {labelData}:{' '}
        {new Date(dataExibida).toLocaleString('pt-BR', {
          day: '2-digit', month: '2-digit', year: 'numeric',
          hour: '2-digit', minute: '2-digit',
        })}
      </p>
    </button>
  )
}

function apiMsg(err: unknown): string {
  return (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    ?? 'Erro ao carregar ordens de manutenção.'
}
