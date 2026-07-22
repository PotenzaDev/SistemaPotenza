import { useState } from 'react'
import { Play, Pause, CheckCircle, X, Loader2, Wrench, Plus, ClipboardList, Package, Hammer } from 'lucide-react'
import {
  type OrdemManutencao,
  type StatusOrdem,
  atualizarStatusOrdem,
  updateOrdemObservacoes,
  adicionarPeca,
  removerPeca,
  adicionarServico,
  removerServico,
} from '@/api/manutencao'

export interface OrdemManutencaoModalProps {
  ordem: OrdemManutencao
  onClose: () => void
  onUpdate: (updated: OrdemManutencao) => void
}

const LABELS_PRIORIDADE: Record<string, string> = {
  critica: 'Crítica',
  alta: 'Alta',
  normal: 'Normal',
  baixa: 'Baixa',
}

const LABELS_STATUS: Record<string, string> = {
  aberta: 'Aberta',
  em_atendimento: 'Em Atendimento',
  pausada: 'Pausada',
  concluida: 'Concluída',
  cancelada: 'Cancelada',
}

const CLASSES_PRIORIDADE: Record<string, string> = {
  critica: 'bg-red-500/15 text-red-400 border border-red-500/30',
  alta: 'bg-orange-500/15 text-orange-400 border border-orange-500/30',
  normal: 'bg-blue-500/15 text-blue-400 border border-blue-500/30',
  baixa: 'bg-slate-700/50 text-slate-400',
}

const CLASSES_STATUS: Record<string, string> = {
  aberta: 'bg-yellow-500/15 text-yellow-400',
  em_atendimento: 'bg-blue-500/15 text-blue-400',
  pausada: 'bg-orange-500/15 text-orange-400',
  concluida: 'bg-emerald-500/15 text-emerald-400',
  cancelada: 'bg-slate-700/50 text-slate-500',
}

function BadgePrioridade({ prioridade }: { prioridade: string }) {
  const cls = CLASSES_PRIORIDADE[prioridade] ?? CLASSES_PRIORIDADE.normal
  return (
    <span className={'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' + cls}>
      {LABELS_PRIORIDADE[prioridade] ?? prioridade}
    </span>
  )
}

function BadgeStatus({ status }: { status: string }) {
  const cls = CLASSES_STATUS[status] ?? CLASSES_STATUS.aberta
  return (
    <span className={'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' + cls}>
      {LABELS_STATUS[status] ?? status}
    </span>
  )
}

function formatarData(data: string | null | undefined): string {
  if (!data) return '—'
  return new Date(data).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatarDataSimples(data: string): string {
  return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR')
}

function formatarMoeda(valor: number): string {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function apiMsg(err: unknown): string {
  return (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    ?? 'Erro inesperado. Tente novamente.'
}

export function OrdemManutencaoModal({ ordem, onClose, onUpdate }: OrdemManutencaoModalProps) {
  const [obs, setObs] = useState(ordem.observacoes ?? '')
  const [savingObs, setSavingObs] = useState(false)
  const [loadingAction, setLoadingAction] = useState<string | null>(null)
  const [erroApi, setErroApi] = useState<string | null>(null)
  const [activeTab, setActiveTab] = useState<'pecas' | 'servicos'>('pecas')
  const [novaPeca, setNovaPeca] = useState({ descricao: '', quantidade: '', preco: '' })
  const [adicionandoPeca, setAdicionandoPeca] = useState(false)
  const [removendoPecaId, setRemovendoPecaId] = useState<number | null>(null)
  const [novoServico, setNovoServico] = useState({ servico: '', descricao: '', valor: '', data: '' })
  const [adicionandoServico, setAdicionandoServico] = useState(false)
  const [removendoServicoId, setRemovendoServicoId] = useState<number | null>(null)
  const [confirmarAcao, setConfirmarAcao] = useState<'concluir' | 'cancelar' | null>(null)

  const isLoading = loadingAction !== null
  const isFinalizada = ordem.status === 'concluida' || ordem.status === 'cancelada'

  const totalPecas = ordem.pecas.reduce((sum, p) => sum + p.quantidade * p.preco_unitario, 0)
  const totalServicos = ordem.servicos.reduce((sum, s) => sum + s.valor, 0)
  const totalGeral = totalPecas + totalServicos

  async function handleSalvarObs() {
    setSavingObs(true)
    setErroApi(null)
    try {
      const updated = await updateOrdemObservacoes(ordem.id, obs)
      onUpdate(updated)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setSavingObs(false)
    }
  }

  async function handleAcao(novoStatus: StatusOrdem, actionKey: string) {
    setLoadingAction(actionKey)
    setErroApi(null)
    try {
      const updated = await atualizarStatusOrdem(ordem.id, novoStatus)
      onUpdate(updated)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setLoadingAction(null)
    }
  }

  async function handleAdicionarPeca() {
    if (!novaPeca.descricao.trim() || !novaPeca.quantidade || !novaPeca.preco) return
    setAdicionandoPeca(true)
    setErroApi(null)
    try {
      const updated = await adicionarPeca(ordem.id, {
        descricao: novaPeca.descricao.trim(),
        quantidade: Number(novaPeca.quantidade),
        preco_unitario: Number(novaPeca.preco),
      })
      setNovaPeca({ descricao: '', quantidade: '', preco: '' })
      onUpdate(updated)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setAdicionandoPeca(false)
    }
  }

  async function handleRemoverPeca(pecaId: number) {
    setRemovendoPecaId(pecaId)
    setErroApi(null)
    try {
      const updated = await removerPeca(ordem.id, pecaId)
      onUpdate(updated)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setRemovendoPecaId(null)
    }
  }

  async function handleAdicionarServico() {
    if (!novoServico.servico.trim() || !novoServico.valor || !novoServico.data) return
    setAdicionandoServico(true)
    setErroApi(null)
    try {
      const updated = await adicionarServico(ordem.id, {
        servico: novoServico.servico.trim(),
        descricao: novoServico.descricao.trim(),
        valor: Number(novoServico.valor),
        data: novoServico.data,
      })
      setNovoServico({ servico: '', descricao: '', valor: '', data: '' })
      onUpdate(updated)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setAdicionandoServico(false)
    }
  }

  async function handleRemoverServico(servicoId: number) {
    setRemovendoServicoId(servicoId)
    setErroApi(null)
    try {
      const updated = await removerServico(ordem.id, servicoId)
      onUpdate(updated)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setRemovendoServicoId(null)
    }
  }

  return (
    <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
      <div className="w-full max-w-2xl bg-[#0f1923] border border-white/5 rounded-xl shadow-xl flex flex-col max-h-[90vh]">

        {/* Header */}
        <div className="flex items-center justify-between gap-4 px-5 py-4 border-b border-white/5 shrink-0">
          <div className="flex items-center gap-2 min-w-0">
            <Wrench className="w-4 h-4 text-[#00aa84] shrink-0" />
            <h2 className="text-base font-bold text-white">{'OS #' + ordem.id}</h2>
            <span className="text-slate-600 hidden sm:block">—</span>
            <span className="text-sm text-slate-400 truncate hidden sm:block">{ordem.maquina.nome}</span>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors shrink-0"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Body */}
        <div className="px-5 py-4 space-y-4 overflow-y-auto">

          {/* Erro global */}
          {erroApi && (
            <div className="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
              <p className="text-xs text-red-400">{erroApi}</p>
            </div>
          )}

          {/* Cabeçalho informativo */}
          <div className="bg-white/[0.03] border border-white/5 rounded-lg px-4 py-3 space-y-2">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Máquina</span>
              <span className="text-sm font-medium text-white">{ordem.maquina.nome}</span>
              <span className="text-slate-600">|</span>
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Setor</span>
              <span className="text-sm text-slate-300">{ordem.maquina.etapa_fluxo?.nome ?? 'Sem setor'}</span>
            </div>
            <div className="flex items-center gap-2 flex-wrap">
              <BadgePrioridade prioridade={ordem.prioridade} />
              <BadgeStatus status={ordem.status} />
            </div>
          </div>

          {/* Campos informativos */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
            <div className="flex flex-col gap-0.5">
              <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Solicitante</span>
              <span className="text-sm text-slate-300">{ordem.solicitante}</span>
            </div>
            <div className="flex flex-col gap-0.5">
              <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Solicitado em</span>
              <span className="text-sm text-slate-300">{formatarData(ordem.solicitado_em)}</span>
            </div>
            <div className="col-span-2 flex flex-col gap-0.5">
              <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Motivo</span>
              <span className="text-sm text-slate-300">{ordem.motivo}</span>
            </div>
            {ordem.atendido_em && (
              <div className="flex flex-col gap-0.5">
                <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Atendido em</span>
                <span className="text-sm text-slate-300">{formatarData(ordem.atendido_em)}</span>
              </div>
            )}
            {ordem.concluido_em && (
              <div className="flex flex-col gap-0.5">
                <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Concluído em</span>
                <span className="text-sm text-emerald-400 font-medium">{formatarData(ordem.concluido_em)}</span>
              </div>
            )}
          </div>

          {/* ── MODO RELATÓRIO (OS concluída ou cancelada) ─────────────────── */}
          {isFinalizada && (
            <div className="border-t border-white/5 pt-4 space-y-4">

              {/* O que foi feito */}
              <div className="space-y-1.5">
                <div className="flex items-center gap-2">
                  <ClipboardList className="w-3.5 h-3.5 text-slate-500" />
                  <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">O que foi feito</p>
                </div>
                {ordem.observacoes ? (
                  <div className="bg-white/[0.03] border-l-2 border-[#00aa84]/50 rounded-r-lg px-4 py-3">
                    <p className="text-sm text-slate-200 whitespace-pre-wrap leading-relaxed">{ordem.observacoes}</p>
                  </div>
                ) : (
                  <p className="text-xs text-slate-600 italic pl-1">Nenhuma observação registrada.</p>
                )}
              </div>

              {/* Peças utilizadas */}
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <Package className="w-3.5 h-3.5 text-slate-500" />
                  <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Peças Utilizadas
                    {ordem.pecas.length > 0 && (
                      <span className="ml-1.5 text-slate-600 font-normal normal-case tracking-normal">
                        ({ordem.pecas.length})
                      </span>
                    )}
                  </p>
                </div>

                {ordem.pecas.length === 0 ? (
                  <p className="text-xs text-slate-600 italic pl-1">Nenhuma peça registrada.</p>
                ) : (
                  <div className="rounded-lg border border-white/5 overflow-hidden">
                    <div className="grid grid-cols-[1fr_52px_84px_88px] gap-2 px-3 py-2 bg-white/[0.02] text-[10px] text-slate-600 font-semibold uppercase tracking-wider">
                      <span>Descrição</span>
                      <span className="text-right">Qtd</span>
                      <span className="text-right">Unit.</span>
                      <span className="text-right">Subtotal</span>
                    </div>
                    {ordem.pecas.map((peca, i) => (
                      <div
                        key={peca.id}
                        className={`grid grid-cols-[1fr_52px_84px_88px] gap-2 items-center px-3 py-2.5 ${
                          i % 2 === 0 ? 'bg-white/[0.015]' : ''
                        }`}
                      >
                        <span className="text-sm text-slate-200">{peca.descricao}</span>
                        <span className="text-xs text-slate-400 text-right">{peca.quantidade}×</span>
                        <span className="text-xs text-slate-400 text-right">{formatarMoeda(peca.preco_unitario)}</span>
                        <span className="text-sm text-slate-300 text-right font-medium">
                          {formatarMoeda(peca.quantidade * peca.preco_unitario)}
                        </span>
                      </div>
                    ))}
                    <div className="flex justify-between items-center px-3 py-2 border-t border-white/5 bg-white/[0.02]">
                      <span className="text-xs text-slate-500">Subtotal peças</span>
                      <span className="text-sm font-semibold text-white">{formatarMoeda(totalPecas)}</span>
                    </div>
                  </div>
                )}
              </div>

              {/* Serviços realizados */}
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <Hammer className="w-3.5 h-3.5 text-slate-500" />
                  <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Serviços Realizados
                    {ordem.servicos.length > 0 && (
                      <span className="ml-1.5 text-slate-600 font-normal normal-case tracking-normal">
                        ({ordem.servicos.length})
                      </span>
                    )}
                  </p>
                </div>

                {ordem.servicos.length === 0 ? (
                  <p className="text-xs text-slate-600 italic pl-1">Nenhum serviço registrado.</p>
                ) : (
                  <div className="space-y-2">
                    {ordem.servicos.map(servico => (
                      <div key={servico.id} className="bg-white/[0.03] border border-white/5 rounded-lg px-4 py-3 space-y-1.5">
                        <div className="flex items-start justify-between gap-3">
                          <span className="text-sm font-semibold text-white">{servico.servico}</span>
                          <div className="shrink-0 text-right">
                            <p className="text-sm font-semibold text-[#00aa84]">{formatarMoeda(servico.valor)}</p>
                            <p className="text-[10px] text-slate-500">{formatarDataSimples(servico.data)}</p>
                          </div>
                        </div>
                        {servico.descricao && (
                          <p className="text-xs text-slate-400 leading-relaxed">{servico.descricao}</p>
                        )}
                      </div>
                    ))}
                    <div className="flex justify-between items-center px-1 pt-1">
                      <span className="text-xs text-slate-500">Subtotal serviços</span>
                      <span className="text-sm font-semibold text-white">{formatarMoeda(totalServicos)}</span>
                    </div>
                  </div>
                )}
              </div>

              {/* Custo total geral */}
              {(ordem.pecas.length > 0 || ordem.servicos.length > 0) && (
                <div className="flex justify-between items-center bg-white/[0.04] border border-white/8 rounded-lg px-4 py-3">
                  <span className="text-sm font-semibold text-slate-300">Custo Total</span>
                  <span className="text-base font-bold text-white">{formatarMoeda(totalGeral)}</span>
                </div>
              )}

            </div>
          )}

          {/* ── MODO EDIÇÃO (OS ativas) ────────────────────────────────────── */}
          {!isFinalizada && (
            <>
              {/* Observações */}
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <ClipboardList className="w-3.5 h-3.5 text-slate-500" />
                  <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Observações</p>
                </div>
                <textarea
                  value={obs}
                  onChange={e => setObs(e.target.value)}
                  rows={3}
                  placeholder="Descreva o que foi feito, o problema encontrado, próximos passos…"
                  className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition resize-none"
                />
                <button
                  type="button"
                  onClick={() => void handleSalvarObs()}
                  disabled={savingObs}
                  className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold text-white bg-white/5 hover:bg-white/10 border border-white/10 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {savingObs && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                  Salvar
                </button>
              </div>

              {/* Tabs: Peças / Serviços */}
              <div className="border-t border-white/5 pt-4 space-y-3">
                <div className="flex gap-1 border-b border-white/5">
                  <button
                    type="button"
                    onClick={() => setActiveTab('pecas')}
                    className={`flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors ${
                      activeTab === 'pecas'
                        ? 'bg-white/5 text-white border border-b-0 border-white/10'
                        : 'text-slate-500 hover:text-slate-300'
                    }`}
                  >
                    <Package className="w-3 h-3" />
                    Peças
                    {ordem.pecas.length > 0 && (
                      <span className="px-1.5 py-0.5 rounded-full text-[10px] bg-white/10 text-slate-400">
                        {ordem.pecas.length}
                      </span>
                    )}
                  </button>
                  <button
                    type="button"
                    onClick={() => setActiveTab('servicos')}
                    className={`flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors ${
                      activeTab === 'servicos'
                        ? 'bg-white/5 text-white border border-b-0 border-white/10'
                        : 'text-slate-500 hover:text-slate-300'
                    }`}
                  >
                    <Hammer className="w-3 h-3" />
                    Serviços
                    {ordem.servicos.length > 0 && (
                      <span className="px-1.5 py-0.5 rounded-full text-[10px] bg-white/10 text-slate-400">
                        {ordem.servicos.length}
                      </span>
                    )}
                  </button>
                </div>

                {/* Aba Peças */}
                {activeTab === 'pecas' && (
                  <div className="space-y-3">
                    <div className="flex gap-2 flex-wrap">
                      <input
                        type="text"
                        placeholder="Descrição da peça"
                        value={novaPeca.descricao}
                        onChange={e => setNovaPeca(p => ({ ...p, descricao: e.target.value }))}
                        className="flex-1 min-w-[130px] px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <input
                        type="number"
                        min="0.001"
                        step="any"
                        placeholder="Qtd"
                        value={novaPeca.quantidade}
                        onChange={e => setNovaPeca(p => ({ ...p, quantidade: e.target.value }))}
                        className="w-20 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="R$ preço"
                        value={novaPeca.preco}
                        onChange={e => setNovaPeca(p => ({ ...p, preco: e.target.value }))}
                        className="w-28 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <button
                        type="button"
                        onClick={() => void handleAdicionarPeca()}
                        disabled={adicionandoPeca || !novaPeca.descricao.trim() || !novaPeca.quantidade || !novaPeca.preco}
                        className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      >
                        {adicionandoPeca ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Plus className="w-3.5 h-3.5" />}
                        Adicionar
                      </button>
                    </div>

                    {ordem.pecas.length === 0 && (
                      <p className="text-xs text-slate-600 italic">Nenhuma peça registrada.</p>
                    )}

                    {ordem.pecas.length > 0 && (
                      <div className="space-y-1.5">
                        <div className="grid grid-cols-[1fr_52px_80px_84px_28px] gap-2 px-2 text-xs text-slate-600 font-medium">
                          <span>Descrição</span>
                          <span className="text-right">Qtd</span>
                          <span className="text-right">Unit.</span>
                          <span className="text-right">Subtotal</span>
                          <span />
                        </div>
                        {ordem.pecas.map(peca => (
                          <div key={peca.id} className="grid grid-cols-[1fr_52px_80px_84px_28px] gap-2 items-center bg-white/[0.03] border border-white/5 rounded-lg px-2 py-2">
                            <span className="text-sm text-slate-200 truncate">{peca.descricao}</span>
                            <span className="text-xs text-slate-400 text-right">{peca.quantidade}×</span>
                            <span className="text-xs text-slate-400 text-right">{formatarMoeda(peca.preco_unitario)}</span>
                            <span className="text-xs text-slate-300 text-right font-medium">{formatarMoeda(peca.quantidade * peca.preco_unitario)}</span>
                            <button
                              type="button"
                              onClick={() => void handleRemoverPeca(peca.id)}
                              disabled={removendoPecaId === peca.id}
                              className="flex items-center justify-center p-1 rounded text-slate-500 hover:text-red-400 hover:bg-red-500/10 disabled:opacity-50 transition-colors"
                            >
                              {removendoPecaId === peca.id
                                ? <Loader2 className="w-3 h-3 animate-spin" />
                                : <X className="w-3 h-3" />}
                            </button>
                          </div>
                        ))}
                        <div className="flex justify-end px-2 pt-1">
                          <span className="text-sm font-semibold text-white">
                            Total: {formatarMoeda(totalPecas)}
                          </span>
                        </div>
                      </div>
                    )}
                  </div>
                )}

                {/* Aba Serviços */}
                {activeTab === 'servicos' && (
                  <div className="space-y-3">
                    <div className="flex gap-2 flex-wrap">
                      <input
                        type="text"
                        placeholder="Nome do serviço"
                        value={novoServico.servico}
                        onChange={e => setNovoServico(s => ({ ...s, servico: e.target.value }))}
                        className="flex-1 min-w-[130px] px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <input
                        type="text"
                        placeholder="Descrição (opcional)"
                        value={novoServico.descricao}
                        onChange={e => setNovoServico(s => ({ ...s, descricao: e.target.value }))}
                        className="flex-1 min-w-[130px] px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="R$ valor"
                        value={novoServico.valor}
                        onChange={e => setNovoServico(s => ({ ...s, valor: e.target.value }))}
                        className="w-28 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <input
                        type="date"
                        value={novoServico.data}
                        onChange={e => setNovoServico(s => ({ ...s, data: e.target.value }))}
                        className="w-36 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <button
                        type="button"
                        onClick={() => void handleAdicionarServico()}
                        disabled={adicionandoServico || !novoServico.servico.trim() || !novoServico.valor || !novoServico.data}
                        className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      >
                        {adicionandoServico ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Plus className="w-3.5 h-3.5" />}
                        Adicionar
                      </button>
                    </div>

                    {ordem.servicos.length === 0 && (
                      <p className="text-xs text-slate-600 italic">Nenhum serviço registrado.</p>
                    )}

                    {ordem.servicos.length > 0 && (
                      <div className="space-y-2">
                        {ordem.servicos.map(servico => (
                          <div key={servico.id} className="bg-white/[0.03] border border-white/5 rounded-lg px-3 py-2.5 space-y-1">
                            <div className="flex items-start gap-2">
                              <div className="flex-1 min-w-0 space-y-0.5">
                                <span className="text-sm text-slate-200 font-medium block">{servico.servico}</span>
                                {servico.descricao && (
                                  <span className="text-xs text-slate-400 block">{servico.descricao}</span>
                                )}
                              </div>
                              <div className="shrink-0 text-right">
                                <span className="text-xs text-slate-300 font-medium block">{formatarMoeda(servico.valor)}</span>
                                <span className="text-[10px] text-slate-500 block">{formatarDataSimples(servico.data)}</span>
                              </div>
                              <button
                                type="button"
                                onClick={() => void handleRemoverServico(servico.id)}
                                disabled={removendoServicoId === servico.id}
                                className="flex items-center justify-center p-1 rounded text-slate-500 hover:text-red-400 hover:bg-red-500/10 disabled:opacity-50 transition-colors shrink-0"
                              >
                                {removendoServicoId === servico.id
                                  ? <Loader2 className="w-3 h-3 animate-spin" />
                                  : <X className="w-3 h-3" />}
                              </button>
                            </div>
                          </div>
                        ))}
                        <div className="flex justify-end px-1 pt-1">
                          <span className="text-sm font-semibold text-white">
                            Total: {formatarMoeda(totalServicos)}
                          </span>
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>

              {/* Ações */}
              <div className="border-t border-white/5 pt-4 space-y-2">
                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Ações</p>
                <div className="flex flex-wrap gap-2">

                  {ordem.status === 'aberta' && (
                    <button
                      type="button"
                      onClick={() => void handleAcao('em_atendimento', 'iniciar')}
                      disabled={isLoading}
                      className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      {loadingAction === 'iniciar' ? <Loader2 className="w-4 h-4 animate-spin" /> : <Play className="w-4 h-4" />}
                      Iniciar
                    </button>
                  )}

                  {ordem.status === 'pausada' && (
                    <button
                      type="button"
                      onClick={() => void handleAcao('em_atendimento', 'retomar')}
                      disabled={isLoading}
                      className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      {loadingAction === 'retomar' ? <Loader2 className="w-4 h-4 animate-spin" /> : <Play className="w-4 h-4" />}
                      Retomar
                    </button>
                  )}

                  {ordem.status === 'em_atendimento' && (
                    <button
                      type="button"
                      onClick={() => void handleAcao('pausada', 'pausar')}
                      disabled={isLoading}
                      className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      {loadingAction === 'pausar' ? <Loader2 className="w-4 h-4 animate-spin" /> : <Pause className="w-4 h-4" />}
                      Pausar
                    </button>
                  )}

                  {(ordem.status === 'em_atendimento' || ordem.status === 'pausada') && (
                    <button
                      type="button"
                      onClick={() => setConfirmarAcao('concluir')}
                      disabled={isLoading || confirmarAcao !== null}
                      className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      <CheckCircle className="w-4 h-4" />
                      Concluir
                    </button>
                  )}

                  {ordem.status !== 'concluida' && ordem.status !== 'cancelada' && (
                    <button
                      type="button"
                      onClick={() => setConfirmarAcao('cancelar')}
                      disabled={isLoading || confirmarAcao !== null}
                      className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-red-500/10 text-red-400 hover:bg-red-500/20 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      <X className="w-4 h-4" />
                      Cancelar OS
                    </button>
                  )}

                </div>

                {confirmarAcao && (
                  <div className={`flex items-center gap-3 rounded-lg border px-4 py-3 ${confirmarAcao === 'concluir' ? 'bg-emerald-500/10 border-emerald-500/20' : 'bg-red-500/10 border-red-500/20'}`}>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-white">
                        {confirmarAcao === 'concluir' ? 'Concluir a OS?' : 'Cancelar a OS?'}
                      </p>
                      <p className="text-xs text-slate-400 mt-0.5">
                        {confirmarAcao === 'concluir'
                          ? 'A OS será marcada como concluída.'
                          : 'A OS será cancelada e não poderá ser reaberta.'}
                      </p>
                    </div>
                    <div className="flex gap-2 shrink-0">
                      <button
                        type="button"
                        onClick={() => setConfirmarAcao(null)}
                        disabled={isLoading}
                        className="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 bg-white/5 hover:bg-white/10 disabled:opacity-50 transition-colors"
                      >
                        Voltar
                      </button>
                      <button
                        type="button"
                        onClick={() => { setConfirmarAcao(null); void handleAcao(confirmarAcao === 'concluir' ? 'concluida' : 'cancelada', confirmarAcao) }}
                        disabled={isLoading}
                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors ${confirmarAcao === 'concluir' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-red-600 hover:bg-red-500'}`}
                      >
                        {isLoading && <Loader2 className="w-3 h-3 animate-spin" />}
                        Confirmar
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </>
          )}

        </div>
      </div>
    </div>
  )
}
