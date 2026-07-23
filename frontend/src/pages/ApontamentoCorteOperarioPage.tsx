import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Loader2, LogOut, CheckCircle2, RotateCcw,
  AlertCircle, Cpu, ScanLine, Timer, PackageCheck, QrCode,
  Flag, Pause, Ban, Bell,
} from 'lucide-react'
import {
  getSessaoAtiva, encerrarSessao, getTurnoHoje, encerrarTurno,
  pausarSessao, cancelarSessao, type Sessao, type TurnoHoje,
} from '@/api/sessao'
import {
  getApontamentosAtivos,
  getFichasRecentes,
  pausarApontamento,
  retomarApontamento,
  pausarSistemaBeacon,
  type Apontamento,
  type FichaApontamento,
} from '@/api/apontamento'
import { biparCorte, getChecklistLote, finalizarApontamentoCorte, type ChecklistLoteItem } from '@/api/apontamentoCorte'
import { getMotivosAtivos, type MotivoPausa } from '@/api/motivosPausa'
import { chamarSuporte } from '@/api/suporte'
import { FichasRecentes } from '@/components/FichasRecentes'
import { BarcodeCard } from '@/components/apontamento/BarcodeCard'
import { BarcodeInline } from '@/components/apontamento/BarcodeInline'
import { BotaoPausar } from '@/components/apontamento/BotaoPausar'
import { ChecklistLoteCorte } from '@/components/apontamento/ChecklistLoteCorte'
import { MotivoPausaModal } from '@/components/apontamento/MotivoPausaModal'
import { PausadoPanel } from '@/components/apontamento/PausadoPanel'
import { useTimerLiquido } from '@/hooks/useTimerLiquido'
import { parseBarcode, BARCODE_LENGTH } from '@/lib/barcode'
import { formatDuracao, apiMsg, mensagemFinalizarTurno, horarioLiberacaoTurno, fmtHoraDate } from '@/lib/apontamentoFormat'

type FaseCorte = 'aguardando' | 'em_producao' | 'em_pausa_producao' | 'revisando' | 'concluido'

export function ApontamentoCorteOperarioPage() {
  const navigate = useNavigate()

  const [sessao, setSessao]                 = useState<Sessao | null>(null)
  const [turnoHoje, setTurnoHoje]           = useState<TurnoHoje | null>(null)
  const [now, setNow]                       = useState(() => new Date())
  const [apontamento, setApontamento]       = useState<Apontamento | null>(null)
  const [checklist, setChecklist]           = useState<ChecklistLoteItem[]>([])
  const [fichasRecentes, setFichasRecentes] = useState<FichaApontamento[]>([])
  const [motivosPausa, setMotivosPausa]     = useState<MotivoPausa[]>([])
  const [loadingInicial, setLoadingInicial] = useState(true)
  const [revisando, setRevisando]           = useState(false)
  const [atualizando, setAtualizando]       = useState(false)
  const [pausando, setPausando]             = useState(false)
  const [retomando, setRetomando]           = useState(false)
  const [encerrando, setEncerrando]         = useState(false)
  const [pausandoSessao, setPausandoSessao]     = useState(false)
  const [cancelando, setCancelando]             = useState(false)
  const [finalizandoTurno, setFinalizandoTurno] = useState(false)
  const [showModalTurno, setShowModalTurno]     = useState(false)
  const [showModalSuporte, setShowModalSuporte] = useState(false)
  const [chamandoSuporte, setChamandoSuporte]   = useState(false)
  const [suporteCooldown, setSuporteCooldown]   = useState(false)
  const [suporteEnviado, setSuporteEnviado]     = useState(false)
  const [erroApi, setErroApi]               = useState<string | null>(null)
  const [showModalPausa, setShowModalPausa] = useState(false)
  const [saiuSemPausar, setSaiuSemPausar]   = useState(false)
  const [barcode, setBarcode]               = useState('')
  const barcodeRef                          = useRef<HTMLInputElement>(null)
  const [qtdsFichas, setQtdsFichas]         = useState<Record<number, string>>({})
  const [showConfirmarParcial1, setShowConfirmarParcial1] = useState(false)
  const [showConfirmarParcial2, setShowConfirmarParcial2] = useState(false)
  const [dadosParcial, setDadosParcial]     = useState<{ totalBipado: number; qtdeTotal: number } | null>(null)

  const fase: FaseCorte = !apontamento
    ? 'aguardando'
    : revisando
      ? 'revisando'
      : apontamento.status === 'finalizado'
        ? 'concluido'
        : apontamento.status === 'em_pausa_producao'
          ? 'em_pausa_producao'
          : 'em_producao'

  const parsedBarcode = barcode.length === BARCODE_LENGTH ? parseBarcode(barcode) : null
  const barcodeOk     = parsedBarcode !== null

  const qtdeTotal   = apontamento?.qtde_total ?? 0
  const totalBipado = apontamento?.fichas.reduce((sum, f) => sum + f.qtd_peca, 0) ?? 0
  const loteZerado  = checklist.length > 0
    ? checklist.every(c => c.falta === 0)
    : (qtdeTotal === 0 || totalBipado >= qtdeTotal)

  const pausas = useMemo(() => apontamento?.pausas ?? [], [apontamento])
  const producaoInicio = apontamento?.producao_fim ? null : (apontamento?.producao_inicio ?? null)
  const timerProducao  = useTimerLiquido(producaoInicio, pausas, 'producao')
  const pausaAtual = useMemo(() => pausas.find(p => p.fim === null), [pausas])

  useEffect(() => {
    Promise.all([
      getSessaoAtiva(),
      getApontamentosAtivos(),
      getFichasRecentes(),
      getMotivosAtivos(),
      getTurnoHoje(),
    ]).then(([s, aps, fr, mp, turno]) => {
      if (!s) { navigate('/operario', { replace: true }); return }
      if (!s.maquina.etapa_fluxo?.apontamento_por_lote) { navigate('/operario/apontamento', { replace: true }); return }

      setSessao(s)
      setFichasRecentes(fr)
      setMotivosPausa(mp)
      setTurnoHoje(turno)

      const ap = aps[0] ?? null
      if (ap) {
        setApontamento(ap)
        const init: Record<number, string> = {}
        ap.fichas.forEach(f => { init[f.id] = String(f.qtd_peca) })
        setQtdsFichas(init)

        if (ap.status === 'em_pausa_producao') {
          const aberta = ap.pausas.find(p => p.fim === null)
          if (aberta?.is_sistema) setSaiuSemPausar(true)
        }
      }
    }).finally(() => setLoadingInicial(false))
  }, [navigate])

  useEffect(() => {
    if (!apontamento) { setChecklist([]); return }
    let ativo = true
    getChecklistLote(apontamento.id).then(c => { if (ativo) setChecklist(c) })
    return () => { ativo = false }
  }, [apontamento?.id, apontamento?.fichas.length])

  useEffect(() => {
    if (fase === 'aguardando' || fase === 'em_producao') {
      setBarcode('')
      setTimeout(() => barcodeRef.current?.focus(), 50)
    }
  }, [fase])

  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 60_000)
    return () => clearInterval(id)
  }, [])

  useEffect(() => {
    if (!apontamento || apontamento.status !== 'em_producao') return
    const handler = () => pausarSistemaBeacon(apontamento.id)
    window.addEventListener('beforeunload', handler)
    return () => window.removeEventListener('beforeunload', handler)
  }, [apontamento?.id, apontamento?.status])

  async function recarregarFichasRecentes() {
    try { setFichasRecentes(await getFichasRecentes()) } catch { /* informativo */ }
  }

  async function handleEncerrar() {
    if (!confirm('Deseja encerrar a sessão desta máquina?')) return
    setEncerrando(true)
    try {
      await encerrarSessao()
      navigate('/operario', { replace: true })
    } catch {
      setEncerrando(false)
    }
  }

  async function handlePausarSessao() {
    if (!confirm('Pausar a sessão? O trabalho em andamento será refeito ao retomar.')) return
    setPausandoSessao(true)
    try {
      await pausarSessao()
      navigate('/operario', { replace: true })
    } catch (err) {
      setErroApi(apiMsg(err))
      setPausandoSessao(false)
    }
  }

  async function handleCancelarSessao() {
    if (!confirm('Cancelar esta sessão? O trabalho em andamento ainda não finalizado será perdido. Apontamentos já finalizados são mantidos.')) return
    setCancelando(true)
    try {
      await cancelarSessao()
      navigate('/operario', { replace: true })
    } catch (err) {
      setErroApi(apiMsg(err))
      setCancelando(false)
    }
  }

  async function handleFinalizarTurno() {
    setFinalizandoTurno(true)
    try {
      await encerrarTurno()
      setShowModalTurno(false)
      navigate('/operario', { replace: true })
    } catch (err) {
      setErroApi(apiMsg(err))
      setShowModalTurno(false)
      setFinalizandoTurno(false)
    }
  }

  async function handleChamarSuporte() {
    setChamandoSuporte(true)
    try {
      await chamarSuporte()
      setShowModalSuporte(false)
      setSuporteEnviado(true)
      setSuporteCooldown(true)
      setTimeout(() => setSuporteEnviado(false), 4000)
      setTimeout(() => setSuporteCooldown(false), 60_000)
    } catch {
      setShowModalSuporte(false)
    } finally {
      setChamandoSuporte(false)
    }
  }

  async function handleBipar() {
    if (!parsedBarcode) return
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await biparCorte({
        cod_peca:    parsedBarcode.cod_peca,
        ordem_lote:  parsedBarcode.ordem_lote,
        qtd_peca:    parsedBarcode.qtd_peca,
        pilha:       parsedBarcode.pilha,
        cod_produto: parsedBarcode.cod_produto,
        cor_codigo:  parsedBarcode.cor_codigo,
      })
      setApontamento(ap)
      setQtdsFichas(prev => ({
        ...prev,
        ...Object.fromEntries(
          ap.fichas.filter(f => !(f.id in prev)).map(f => [f.id, String(f.qtd_peca)])
        ),
      }))
      setBarcode('')
      void recarregarFichasRecentes()
    } catch (err) {
      setErroApi(apiMsg(err))
      setBarcode('')
      setTimeout(() => barcodeRef.current?.focus(), 50)
    } finally {
      setAtualizando(false)
    }
  }

  async function handlePausar(motivoId: number) {
    if (!apontamento) return
    setPausando(true); setErroApi(null)
    try {
      const ap = await pausarApontamento(apontamento.id, motivoId)
      setApontamento(ap)
      setShowModalPausa(false)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setPausando(false)
    }
  }

  async function handleRetomar() {
    if (!apontamento) return
    setRetomando(true); setErroApi(null)
    try {
      const ap = await retomarApontamento(apontamento.id)
      setApontamento(ap)
      setSaiuSemPausar(false)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setRetomando(false)
    }
  }

  async function handleFinalizar(confirmarParcial = false) {
    if (!apontamento) return
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await finalizarApontamentoCorte(apontamento.id, {
        fichas: apontamento.fichas.map(f => ({
          ficha_id:      f.id,
          qtd_produzida: parseInt(qtdsFichas[f.id] ?? '0', 10),
        })),
        confirmarParcial,
      })
      setRevisando(false)
      setApontamento(ap)
    } catch (err) {
      type Resp409 = { requiresConfirmation?: boolean; totalBipado?: number; qtdeTotal?: number }
      const resp = (err as { response?: { status?: number; data?: Resp409 } })?.response
      if (!confirmarParcial && resp?.status === 409 && resp?.data?.requiresConfirmation) {
        setDadosParcial({
          totalBipado: resp.data.totalBipado ?? totalBipado,
          qtdeTotal:   resp.data.qtdeTotal ?? qtdeTotal,
        })
        setShowConfirmarParcial1(true)
      } else {
        setErroApi(apiMsg(err))
      }
    } finally {
      setAtualizando(false)
    }
  }

  function handleConfirmarParcial1() {
    setShowConfirmarParcial1(false)
    setShowConfirmarParcial2(true)
  }

  function handleConfirmarParcial2() {
    setShowConfirmarParcial2(false)
    setDadosParcial(null)
    void handleFinalizar(true)
  }

  async function novoLote() {
    setApontamento(null)
    setChecklist([])
    setQtdsFichas({})
    setErroApi(null)
    setBarcode('')
    setSaiuSemPausar(false)
    await recarregarFichasRecentes()
  }

  if (loadingInicial) {
    return (
      <div className="flex items-center justify-center py-32 text-slate-400 gap-2">
        <Loader2 className="w-5 h-5 animate-spin" />
        <span className="text-sm">Carregando…</span>
      </div>
    )
  }
  if (!sessao) return null

  const podeEncerrar = !apontamento || apontamento.status === 'finalizado'
  const acoesSessaoDesabilitadas = atualizando || pausando || retomando || encerrando || finalizandoTurno || pausandoSessao || cancelando

  const horarioLiberacao   = turnoHoje ? horarioLiberacaoTurno(turnoHoje) : null
  const podeFinalizarTurno = !horarioLiberacao || now >= horarioLiberacao

  return (
    <div className="max-w-2xl mx-auto space-y-5">

      {/* Cabeçalho da sessão */}
      <div className="flex items-center justify-between gap-4 bg-[#0f1923] border border-white/5 rounded-xl px-5 py-4">
        <div className="flex items-start gap-3 min-w-0">
          <div className="mt-0.5 p-2 rounded-lg bg-[#00aa84]/10 shrink-0">
            <Cpu className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div className="min-w-0">
            <p className="text-sm font-semibold text-white truncate">{sessao.maquina.nome}</p>
            {sessao.maquina.etapa_fluxo && (
              <span className="inline-flex items-center mt-1 px-2 py-0.5 rounded-md text-xs font-medium bg-[#00aa84]/10 text-[#00aa84]">
                {sessao.maquina.etapa_fluxo.nome}
              </span>
            )}
          </div>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          <button
            type="button"
            onClick={() => setShowModalTurno(true)}
            disabled={acoesSessaoDesabilitadas || !podeFinalizarTurno}
            title={!podeFinalizarTurno && horarioLiberacao ? `Disponível a partir das ${fmtHoraDate(horarioLiberacao)}` : undefined}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-amber-400 bg-amber-500/5 border border-amber-500/20 hover:bg-amber-500/10 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
          >
            <Flag className="w-3.5 h-3.5" />
            Finalizar Turno
          </button>
          <button
            type="button"
            onClick={handlePausarSessao}
            disabled={acoesSessaoDesabilitadas}
            title="Pausa a sessão; ao retomar, o trabalho em andamento precisará ser refeito"
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 bg-white/5 hover:bg-amber-500/10 hover:text-amber-400 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
          >
            {pausandoSessao ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Pause className="w-3.5 h-3.5" />}
            Pausar Sessão
          </button>
          <button
            type="button"
            onClick={handleEncerrar}
            disabled={encerrando || !podeEncerrar}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 bg-white/5 hover:bg-red-500/10 hover:text-red-400 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
          >
            {encerrando ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <LogOut className="w-3.5 h-3.5" />}
            Encerrar
          </button>
          <button
            type="button"
            onClick={handleCancelarSessao}
            disabled={cancelando}
            title="Cancela a sessão; apontamentos não finalizados são excluídos"
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 bg-white/5 hover:bg-red-500/10 hover:text-red-400 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
          >
            {cancelando ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Ban className="w-3.5 h-3.5" />}
            Cancelar Sessão
          </button>
        </div>
      </div>

      {/* Erro global */}
      {erroApi && (
        <div className="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
          <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
          <p className="text-xs text-red-400">{erroApi}</p>
        </div>
      )}

      {/* FASE: aguardando */}
      {fase === 'aguardando' && (
        <>
          <BarcodeCard
            titulo="Bipar peça do lote"
            subtitulo="Leia o código de barras da primeira peça para identificar o lote"
            barcode={barcode}
            barcodeOk={barcodeOk}
            inputRef={barcodeRef}
            atualizando={atualizando}
            botaoLabel="Bipar"
            botaoIcone={<ScanLine className="w-4 h-4" />}
            onChange={setBarcode}
            onSubmit={handleBipar}
          />
          <FichasRecentes fichas={fichasRecentes} />
        </>
      )}

      {/* FASE: em_producao */}
      {fase === 'em_producao' && apontamento && (
        <>
          <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
            <div className="flex items-center justify-between gap-3 px-5 py-3 border-b border-white/5">
              <div className="flex items-center gap-2 min-w-0">
                <div className="p-1.5 rounded-lg bg-[#00aa84]/10 shrink-0">
                  <Timer className="w-4 h-4 text-[#00aa84]" />
                </div>
                <div className="min-w-0">
                  <p className="text-xs font-semibold text-white truncate">Lote {apontamento.ordem_lote.replace(/^0+/, '')}</p>
                  {apontamento.qtde_total !== null && (
                    <p className="text-xs text-slate-500">
                      <span className="text-[#00aa84] font-medium">{totalBipado}/{apontamento.qtde_total} pç</span>
                    </p>
                  )}
                </div>
              </div>
              <div className="shrink-0 text-right">
                <p className="text-xs text-slate-500 leading-none mb-0.5">produção</p>
                <p className="text-2xl font-mono font-bold tabular-nums text-[#00aa84]">{timerProducao}</p>
              </div>
            </div>
          </div>

          <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
            <div className="flex items-center gap-2 px-5 py-3 border-b border-white/5">
              <QrCode className="w-4 h-4 text-slate-400" />
              <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Bipar próxima peça do lote</p>
            </div>
            <div className="px-5 py-4">
              <BarcodeInline
                barcode={barcode}
                barcodeOk={barcodeOk}
                inputRef={barcodeRef}
                atualizando={atualizando}
                botaoLabel="Confirmar"
                onChange={setBarcode}
                onSubmit={handleBipar}
              />
            </div>
          </div>

          <div className="space-y-3">
            <button
              type="button"
              onClick={() => setRevisando(true)}
              disabled={atualizando}
              className="w-full py-3 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-40 disabled:cursor-not-allowed rounded-xl transition-colors flex items-center justify-center gap-2"
            >
              <CheckCircle2 className="w-4 h-4" />
              {loteZerado ? 'Finalizar Produção' : 'Finalizar Produção (parcial)'}
            </button>
            {!loteZerado && (
              <p className="text-xs text-center text-slate-500">
                {totalBipado} de {qtdeTotal || '?'} peças bipadas
              </p>
            )}
            <BotaoPausar
              label="Pausar Produção"
              disabled={atualizando}
              onClick={() => setShowModalPausa(true)}
            />
          </div>

          {checklist.length > 0 && <ChecklistLoteCorte itens={checklist} />}

          <FichasRecentes fichas={fichasRecentes} />
        </>
      )}

      {/* FASE: em_pausa_producao */}
      {fase === 'em_pausa_producao' && apontamento && (
        <>
          <PausadoPanel
            apontamento={apontamento}
            pausaAtual={pausaAtual}
            saiuSemPausar={saiuSemPausar}
            retomando={retomando}
            onRetomar={handleRetomar}
          />
          {checklist.length > 0 && <ChecklistLoteCorte itens={checklist} />}
        </>
      )}

      {/* FASE: revisando */}
      {fase === 'revisando' && apontamento && (
        <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
          <div className="flex items-center gap-3 px-6 pt-6 pb-4 border-b border-white/5">
            <div className="p-2 rounded-lg bg-[#00aa84]/10">
              <PackageCheck className="w-5 h-5 text-[#00aa84]" />
            </div>
            <div>
              <p className="text-sm font-semibold text-white">Registrar produção</p>
              <p className="text-xs text-slate-500 mt-0.5">Confirme as quantidades produzidas por ficha</p>
            </div>
          </div>
          <div className="px-6 py-5 space-y-4">
            <div className="flex items-center justify-between bg-white/[0.03] rounded-lg px-3 py-2.5">
              <p className="text-xs text-slate-500">Tempo de produção</p>
              <p className="text-sm font-mono font-semibold text-[#00aa84]">{timerProducao}</p>
            </div>
            <div className="space-y-3">
              {apontamento.fichas.map(f => (
                <div key={f.id} className="bg-white/[0.03] border border-white/5 rounded-lg px-4 py-3 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-mono font-semibold text-white">{f.cod_peca}</span>
                    <span className="text-xs text-slate-500">pilha {f.pilha} · {f.qtd_peca} pç/ficha</span>
                  </div>
                  <div className="space-y-1">
                    <label className="text-xs font-medium text-slate-400">Qtd. produzida</label>
                    <input
                      type="number"
                      min={0}
                      value={qtdsFichas[f.id] ?? ''}
                      onChange={e => setQtdsFichas(prev => ({ ...prev, [f.id]: e.target.value }))}
                      className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm font-semibold text-white text-center placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
                    />
                  </div>
                </div>
              ))}
            </div>
            <div className="flex gap-3 pt-1">
              <button
                type="button"
                onClick={() => setRevisando(false)}
                className="flex-1 py-2.5 text-sm font-medium text-slate-400 bg-white/5 hover:bg-white/10 rounded-lg transition-colors"
              >
                Voltar
              </button>
              <button
                type="button"
                onClick={() => handleFinalizar()}
                disabled={atualizando}
                className="flex-1 py-2.5 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                {atualizando
                  ? <><Loader2 className="w-4 h-4 animate-spin" />Salvando…</>
                  : <><CheckCircle2 className="w-4 h-4" />Confirmar</>}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* FASE: concluido */}
      {fase === 'concluido' && apontamento && (
        <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
          <div className="flex items-center gap-3 px-6 pt-6 pb-4 border-b border-white/5">
            <div className="p-2 rounded-lg bg-emerald-500/10">
              <CheckCircle2 className="w-5 h-5 text-emerald-400" />
            </div>
            <div>
              <p className="text-sm font-semibold text-white">Lote concluído</p>
              <p className="text-xs text-slate-500 mt-0.5">lote {apontamento.ordem_lote.replace(/^0+/, '')}</p>
            </div>
          </div>
          <div className="px-6 py-5 space-y-4">
            <div className="bg-white/[0.03] rounded-lg px-3 py-2.5 space-y-0.5">
              <p className="text-xs text-slate-500">Tempo produção</p>
              <p className="text-sm font-semibold text-white">{formatDuracao(apontamento.producao_duracao_segundos ?? null)}</p>
            </div>
            {apontamento.fichas.length > 0 && (
              <div className="space-y-2">
                <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Fichas produzidas</p>
                <div className="divide-y divide-white/5 bg-white/[0.02] rounded-lg overflow-hidden">
                  {apontamento.fichas.map(f => (
                    <div key={f.id} className="grid grid-cols-3 items-center px-4 py-2.5">
                      <span className="text-xs font-mono text-white">
                        {f.cod_peca}
                        <span className="block text-slate-500">pilha {f.pilha}</span>
                      </span>
                      <span className="text-xs font-mono text-slate-400 text-center">{formatDuracao(f.duracao_segundos)}</span>
                      <span className="text-xs font-semibold text-[#00aa84] text-right">
                        {f.qtd_produzida !== null ? `${f.qtd_produzida} pç` : '—'}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
            <button
              type="button"
              onClick={novoLote}
              className="w-full py-2.5 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              <RotateCcw className="w-4 h-4" />Bipar novo lote
            </button>
          </div>
        </div>
      )}

      {/* Modal de finalizar turno */}
      {showModalTurno && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={finalizandoTurno ? undefined : () => setShowModalTurno(false)} />
          <div className="relative z-10 w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl px-6 py-6 space-y-4">
            <div>
              <p className="text-base font-semibold text-white">Finalizar turno?</p>
              <p className="text-sm text-slate-400 mt-1">
                {mensagemFinalizarTurno(fase)}
              </p>
            </div>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setShowModalTurno(false)}
                disabled={finalizandoTurno}
                className="flex-1 py-2.5 text-sm font-medium text-slate-400 bg-white/5 hover:bg-white/10 disabled:opacity-40 rounded-lg transition-colors"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={handleFinalizarTurno}
                disabled={finalizandoTurno}
                className="flex-1 py-2.5 text-sm font-semibold text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-50 rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                {finalizandoTurno
                  ? <><Loader2 className="w-4 h-4 animate-spin" />Finalizando…</>
                  : <><Flag className="w-4 h-4" />Finalizar Turno</>}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Botão fixo — chamar suporte (visível em todas as fases exceto concluído) */}
      {fase !== 'concluido' && (
        <button
          type="button"
          onClick={() => !suporteCooldown && setShowModalSuporte(true)}
          disabled={suporteCooldown}
          title={suporteCooldown ? 'Suporte já solicitado. Aguarde 1 minuto.' : 'Chamar suporte'}
          className={[
            'fixed bottom-6 right-6 z-50',
            'flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-medium',
            'border shadow-lg transition-colors',
            suporteEnviado
              ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
              : suporteCooldown
                ? 'bg-white/5 border-white/10 text-slate-500 cursor-not-allowed'
                : 'bg-orange-500/5 border-orange-500/20 text-orange-400 hover:bg-orange-500/10',
          ].join(' ')}
        >
          {suporteEnviado
            ? <><CheckCircle2 className="w-3.5 h-3.5" />Suporte chamado</>
            : <><Bell className="w-3.5 h-3.5" />Suporte</>}
        </button>
      )}

      {/* Modal de confirmação — chamar suporte */}
      {showModalSuporte && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <div className="flex items-center gap-2 px-5 py-4 border-b border-white/5">
              <Bell className="w-4 h-4 text-orange-400" />
              <p className="text-sm font-semibold text-white">Chamar suporte?</p>
            </div>
            <div className="p-5 space-y-4">
              <p className="text-sm text-slate-400 leading-relaxed">
                Um aviso será enviado ao administrador com o nome desta máquina e o seu nome.
              </p>
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => setShowModalSuporte(false)}
                  disabled={chamandoSuporte}
                  className="flex-1 px-4 py-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/5 text-sm font-medium text-slate-300 transition-all disabled:opacity-40"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  onClick={handleChamarSuporte}
                  disabled={chamandoSuporte}
                  className="flex-1 px-4 py-3 rounded-xl bg-orange-500/20 hover:bg-orange-500/30 border border-orange-500/20 text-sm font-medium text-orange-300 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                >
                  {chamandoSuporte
                    ? <><Loader2 className="w-3.5 h-3.5 animate-spin" />Enviando…</>
                    : <><Bell className="w-3.5 h-3.5" />Confirmar</>}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal de motivo de pausa */}
      {showModalPausa && (
        <MotivoPausaModal
          motivos={motivosPausa}
          pausando={pausando}
          onSelect={handlePausar}
          onClose={() => setShowModalPausa(false)}
        />
      )}

      {/* Modal 1/2 — finalizar com peças faltando */}
      {showConfirmarParcial1 && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <div className="flex items-center gap-2 px-5 py-4 border-b border-white/5">
              <AlertCircle className="w-4 h-4 text-amber-400" />
              <p className="text-sm font-semibold text-white">Finalizar faltando peças?</p>
            </div>
            <div className="p-5 space-y-4">
              <p className="text-sm text-slate-400 leading-relaxed">
                {dadosParcial
                  ? `Foram bipadas ${dadosParcial.totalBipado} de ${dadosParcial.qtdeTotal} peças. Deseja finalizar mesmo assim, deixando o restante para um próximo apontamento?`
                  : 'Ainda faltam peças para completar este lote. Deseja finalizar mesmo assim?'}
              </p>
              <div className="flex gap-3">
                <button
                  onClick={() => { setShowConfirmarParcial1(false); setDadosParcial(null) }}
                  className="flex-1 px-4 py-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/5 text-sm font-medium text-slate-300 transition-all"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleConfirmarParcial1}
                  className="flex-1 px-4 py-3 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/20 text-sm font-medium text-amber-300 transition-all flex items-center justify-center gap-2"
                >
                  <AlertCircle className="w-3.5 h-3.5" />
                  Continuar
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal 2/2 — reforço antes de finalizar parcial */}
      {showConfirmarParcial2 && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <div className="flex items-center gap-2 px-5 py-4 border-b border-white/5">
              <AlertCircle className="w-4 h-4 text-red-400" />
              <p className="text-sm font-semibold text-white">Tem certeza?</p>
            </div>
            <div className="p-5 space-y-4">
              <p className="text-sm text-slate-400 leading-relaxed">
                Esta finalização será registrada como parcial. Ao bipar este lote novamente, o apontamento continuará de onde parou.
              </p>
              <div className="flex gap-3">
                <button
                  onClick={() => { setShowConfirmarParcial2(false); setDadosParcial(null) }}
                  className="flex-1 px-4 py-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/5 text-sm font-medium text-slate-300 transition-all"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleConfirmarParcial2}
                  disabled={atualizando}
                  className="flex-1 px-4 py-3 rounded-xl bg-red-500/20 hover:bg-red-500/30 border border-red-500/20 text-sm font-medium text-red-300 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                >
                  {atualizando ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <CheckCircle2 className="w-3.5 h-3.5" />}
                  Confirmar finalização parcial
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
