import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Loader2, LogOut, CheckCircle2, RotateCcw,
  AlertCircle, Cpu, ScanLine, Settings, Play,
  Timer, PackageCheck, QrCode, Flag, Pause, Bell, Ban,
  ClipboardList,
} from 'lucide-react'
import { getSessaoAtiva, getTurnoHoje, encerrarSessao, encerrarTurno, pausarSessao, pausarSessaoOciosa, retomarSessaoOciosa, cancelarSessao, type Sessao, type TurnoHoje } from '@/api/sessao'
import {
  getApontamentosAtivos,
  biparLote,
  segundaPassagem,
  finalizarSetup,
  biparFicha,
  finalizarApontamento,
  finalizarApontamentoSemProducao,
  getFichasRecentes,
  getFichasPorCor,
  getFichaSetup,
  pausarApontamento,
  retomarApontamento,
  pausarSistemaBeacon,
  type Apontamento,
  type FichaApontamento,
  type ResumoFichasPorCor,
} from '@/api/apontamento'
import type { FichaCabecote } from '@/api/fichasCabecote'
import { getMotivosAtivos, type MotivoPausa } from '@/api/motivosPausa'
import { chamarSuporte } from '@/api/suporte'
import { FichasRecentes } from '@/components/FichasRecentes'
import { BarcodeCard } from '@/components/apontamento/BarcodeCard'
import { BarcodeInline } from '@/components/apontamento/BarcodeInline'
import { BotaoPausar } from '@/components/apontamento/BotaoPausar'
import { FaseTimer } from '@/components/apontamento/FaseTimer'
import { FichasDoLote } from '@/components/apontamento/FichasDoLote'
import { FichaSetupPanel } from '@/components/apontamento/FichaSetupPanel'
import { InfoCard } from '@/components/apontamento/InfoCard'
import { MotivoPausaModal } from '@/components/apontamento/MotivoPausaModal'
import { PausadoPanel } from '@/components/apontamento/PausadoPanel'
import { useTimerLiquido } from '@/hooks/useTimerLiquido'
import { parseBarcode, BARCODE_LENGTH } from '@/lib/barcode'
import { formatDuracao, derivarFase, apiMsg, mensagemFinalizarTurno, horarioLiberacaoTurno, fmtHoraDate, STATUS_LABEL, type Fase } from '@/lib/apontamentoFormat'

export function ApontamentoOperarioPage() {
  const navigate = useNavigate()

  const [sessao, setSessao]                 = useState<Sessao | null>(null)
  const [turnoHoje, setTurnoHoje]           = useState<TurnoHoje | null>(null)
  const [now, setNow]                       = useState(() => new Date())
  const [apontamentos, setApontamentos]     = useState<Apontamento[]>([])
  const [focoId, setFocoId]                 = useState<number | null>(null)
  // Override de fase só existe para "finalizando" — uma tela de revisão local
  // (não é status do servidor). Toda outra fase é derivada de apontamento.status.
  const [fasesOverride, setFasesOverride]   = useState<Record<number, Fase>>({})
  const [fichasRecentes, setFichasRecentes] = useState<FichaApontamento[]>([])
  const [resumoPorCor, setResumoPorCor]     = useState<ResumoFichasPorCor[]>([])
  const [fichaSetup, setFichaSetup]         = useState<FichaCabecote | null>(null)
  const [abaSetup, setAbaSetup]             = useState<'setup' | 'ficha_setup'>('setup')
  const [motivosPausa, setMotivosPausa]     = useState<MotivoPausa[]>([])
  const [loadingInicial, setLoadingInicial] = useState(true)
  const [encerrando, setEncerrando]               = useState(false)
  const [pausandoSessao, setPausandoSessao]       = useState(false)
  const [cancelando, setCancelando]               = useState(false)
  const [finalizandoTurno, setFinalizandoTurno]   = useState(false)
  const [showModalTurno, setShowModalTurno]       = useState(false)
  const [atualizando, setAtualizando]       = useState(false)
  const [pausando, setPausando]             = useState(false)
  const [retomando, setRetomando]           = useState(false)
  const [erroApi, setErroApi]               = useState<string | null>(null)
  const [showModalPausa, setShowModalPausa] = useState(false)
  const [showModalPausaOciosa, setShowModalPausaOciosa] = useState(false)
  const [pausandoOciosa, setPausandoOciosa] = useState(false)
  const [retomandoOciosa, setRetomandoOciosa] = useState(false)
  const [saiuSemPausar, setSaiuSemPausar]   = useState(false)
  const [barcode, setBarcode]               = useState('')
  const barcodeRef                          = useRef<HTMLInputElement>(null)
  const [qtdsFichas, setQtdsFichas]         = useState<Record<number, string>>({})
  const [showConfirmarNovaPassagem, setShowConfirmarNovaPassagem]       = useState(false)
  const [dadosNovaPassagem, setDadosNovaPassagem]                       = useState<{ cod_peca: string; ordem_lote: string } | null>(null)
  const [showConfirmarSegundaPassagem, setShowConfirmarSegundaPassagem] = useState(false)
  const [showConfirmarFinalizarParcial1, setShowConfirmarFinalizarParcial1] = useState(false)
  const [showConfirmarFinalizarParcial2, setShowConfirmarFinalizarParcial2] = useState(false)
  const [dadosFinalizacaoParcial, setDadosFinalizacaoParcial] = useState<{ totalBipado: number; qtdeTotal: number } | null>(null)
  const [showModalSuporte, setShowModalSuporte]                         = useState(false)
  const [chamandoSuporte, setChamandoSuporte]                           = useState(false)
  const [suporteCooldown, setSuporteCooldown]                           = useState(false)
  const [suporteEnviado, setSuporteEnviado]                             = useState(false)

  const apontamento = useMemo(
    () => apontamentos.find(a => a.id === focoId) ?? null,
    [apontamentos, focoId]
  )
  const fase: Fase = apontamento ? (fasesOverride[apontamento.id] ?? derivarFase(apontamento)) : 'aguardando'

  const parsedBarcode = barcode.length === BARCODE_LENGTH ? parseBarcode(barcode) : null
  const barcodeOk     = parsedBarcode !== null

  const qtdeTotal   = apontamento?.qtde_total ?? 0
  const totalBipado = apontamento?.fichas.reduce((sum, f) => sum + f.qtd_peca, 0) ?? 0
  const todasCoresCompletas = resumoPorCor.length === 0 || resumoPorCor.every(r => r.falta === 0)
  const loteZerado  = (qtdeTotal === 0 || totalBipado >= qtdeTotal) && todasCoresCompletas

  const pausas = useMemo(() => apontamento?.pausas ?? [], [apontamento])

  const setupInicio    = apontamento?.setup_fim    ? null : (apontamento?.setup_inicio    ?? null)
  const producaoInicio = apontamento?.producao_fim ? null : (apontamento?.producao_inicio ?? null)

  const timerSetup    = useTimerLiquido(setupInicio,    pausas, 'setup')
  const timerProducao = useTimerLiquido(producaoInicio, pausas, 'producao')

  const pausaAtual = useMemo(() => pausas.find(p => p.fim === null), [pausas])

  const retomouAposPausaSessao = useMemo(
    () => pausas.some(p => p.is_sistema && p.motivo === 'Pausa de Sessão' && p.fim !== null),
    [pausas]
  )

  function upsertApontamento(ap: Apontamento) {
    setApontamentos(prev => {
      const idx = prev.findIndex(a => a.id === ap.id)
      if (idx === -1) return [...prev, ap]
      const next = [...prev]
      next[idx] = ap
      return next
    })
  }

  function removerApontamento(id: number) {
    setApontamentos(prev => prev.filter(a => a.id !== id))
    setFasesOverride(prev => {
      if (!(id in prev)) return prev
      const next = { ...prev }
      delete next[id]
      return next
    })
  }

  function focarFinalizando(id: number) {
    setFasesOverride(prev => ({ ...prev, [id]: 'finalizando' }))
  }

  function limparOverride(id: number) {
    setFasesOverride(prev => {
      if (!(id in prev)) return prev
      const next = { ...prev }
      delete next[id]
      return next
    })
  }

  useEffect(() => {
    Promise.all([
      getSessaoAtiva(),
      getApontamentosAtivos(),
      getFichasRecentes(),
      getMotivosAtivos(),
      getTurnoHoje(),
    ]).then(([s, aps, fr, mp, turno]) => {
      if (!s) { navigate('/operario', { replace: true }); return }
      if (s.maquina.etapa_fluxo?.apontamento_por_lote) { navigate('/operario/apontamento-corte', { replace: true }); return }
      setSessao(s)
      setFichasRecentes(fr)
      setMotivosPausa(mp)
      setTurnoHoje(turno)

      if (aps.length === 0) return

      setApontamentos(aps)
      setFocoId(aps[0].id)

      const focoInicial = derivarFase(aps[0])
      if (focoInicial === 'em_pausa_setup' || focoInicial === 'em_pausa_producao') {
        const openPausa = aps[0].pausas.find(p => p.fim === null)
        if (openPausa?.is_sistema) setSaiuSemPausar(true)
      }

      const init: Record<number, string> = {}
      aps.forEach(a => {
        const f = derivarFase(a)
        if (f === 'em_producao' || f === 'em_pausa_producao') {
          a.fichas.forEach(fi => { init[fi.id] = String(fi.qtd_peca) })
        }
      })
      if (Object.keys(init).length > 0) setQtdsFichas(init)
    }).finally(() => setLoadingInicial(false))
  }, [navigate])

  // Mantém o foco válido: se o apontamento focado sumir da lista, foca outro (ou nenhum).
  useEffect(() => {
    if (apontamentos.length === 0) {
      if (focoId !== null) setFocoId(null)
      return
    }
    if (!apontamentos.some(a => a.id === focoId)) {
      setFocoId(apontamentos[0].id)
    }
  }, [apontamentos, focoId])

  useEffect(() => {
    if (fase === 'aguardando' || fase === 'aguardando_ficha' || fase === 'em_producao') {
      setBarcode('')
      setTimeout(() => barcodeRef.current?.focus(), 50)
    }
  }, [fase])

  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 60_000)
    return () => clearInterval(id)
  }, [])

  useEffect(() => {
    if (!apontamento || (fase !== 'em_producao' && fase !== 'em_pausa_producao' && fase !== 'finalizando')) {
      setResumoPorCor([])
      return
    }

    let ativo = true

    getFichasPorCor(apontamento.id).then(resumo => {
      if (ativo) setResumoPorCor(resumo)
    })

    return () => { ativo = false }
  }, [apontamento?.id, apontamento?.fichas.length, fase])

  useEffect(() => {
    if (!apontamento || fase !== 'em_setup') {
      setFichaSetup(null)
      setAbaSetup('setup')
      return
    }

    let ativo = true

    getFichaSetup(apontamento.id).then(ficha => {
      if (ativo) setFichaSetup(ficha)
    })

    return () => { ativo = false }
  }, [apontamento?.id, fase])

  // Auto-pausa via beacon ao fechar a aba: cobre TODOS os apontamentos ativos
  // da sessão (pode haver mais de um, do mesmo lote), não só o focado.
  useEffect(() => {
    const ids = apontamentos
      .filter(a => a.status === 'em_setup' || a.status === 'em_producao')
      .map(a => a.id)

    if (ids.length === 0) return

    const handler = () => ids.forEach(id => pausarSistemaBeacon(id))
    window.addEventListener('beforeunload', handler)
    return () => window.removeEventListener('beforeunload', handler)
  }, [apontamentos])

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
    if (!confirm('Pausar a sessão? O setup em andamento será refeito ao retomar.')) return
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

  async function handleBiparLote() {
    if (!parsedBarcode) return
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await biparLote({
        cod_peca:    parsedBarcode.cod_peca,
        ordem_lote:  parsedBarcode.ordem_lote,
        cod_produto: parsedBarcode.cod_produto,
        cor_codigo:  parsedBarcode.cor_codigo,
      })
      upsertApontamento(ap)
      setFocoId(ap.id)
      setBarcode('')
    } catch (err) {
      const data = (err as { response?: { data?: { loteCompleto?: boolean } } })?.response?.data
      if (data?.loteCompleto) {
        setDadosNovaPassagem({ cod_peca: parsedBarcode.cod_peca, ordem_lote: parsedBarcode.ordem_lote })
        setShowConfirmarNovaPassagem(true)
      } else {
        setErroApi(apiMsg(err))
      }
    } finally {
      setAtualizando(false)
    }
  }

  async function handleConfirmarNovaPassagem() {
    if (!dadosNovaPassagem) return
    setShowConfirmarNovaPassagem(false)
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await segundaPassagem(dadosNovaPassagem)
      upsertApontamento(ap)
      setFocoId(ap.id)
      setBarcode('')
      setSaiuSemPausar(false)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setAtualizando(false)
      setDadosNovaPassagem(null)
    }
  }

  async function handleFinalizarSetup() {
    if (!apontamento) return
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await finalizarSetup(apontamento.id)
      upsertApontamento(ap)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setAtualizando(false)
    }
  }

  async function executarBiparFicha(confirmar: boolean) {
    if (!apontamento || !parsedBarcode) return
    const ap = await biparFicha(apontamento.id, {
      cod_peca:    parsedBarcode.cod_peca,
      ordem_lote:  parsedBarcode.ordem_lote,
      qtd_peca:    parsedBarcode.qtd_peca,
      pilha:       parsedBarcode.pilha,
      cod_produto: parsedBarcode.cod_produto,
      cor_codigo:  parsedBarcode.cor_codigo,
      ...(confirmar && { confirmar: true }),
    })
    upsertApontamento(ap)
    setQtdsFichas(prev => ({
      ...prev,
      ...Object.fromEntries(
        ap.fichas
          .filter(f => !(f.id in prev))
          .map(f => [f.id, String(f.qtd_peca)])
      ),
    }))
    setBarcode('')
    recarregarFichasRecentes()
  }

  async function handleBiparFicha() {
    if (!apontamento || !parsedBarcode) return
    setAtualizando(true); setErroApi(null)
    try {
      await executarBiparFicha(false)
    } catch (err) {
      type Resp409 = { requiresConfirmation?: boolean; message?: string; passagensRealizadas?: number; passagensEsperadas?: number }
      const resp = (err as { response?: { status?: number; data?: Resp409 } })?.response
      if (resp?.status === 409 && resp?.data?.requiresConfirmation) {
        const realizadas = resp.data.passagensRealizadas ?? 1
        const esperadas  = resp.data.passagensEsperadas  ?? 2
        const msg = `${resp.data.message ?? 'Esta pilha já foi bipada neste lote.'}\n\n(${realizadas} de ${esperadas} passagens registradas)`
        if (confirm(msg)) {
          try {
            await executarBiparFicha(true)
          } catch (err2) {
            setErroApi(apiMsg(err2))
            setBarcode('')
          }
        } else {
          setBarcode('')
        }
      } else {
        setErroApi(apiMsg(err))
        setBarcode('')
      }
    } finally {
      setAtualizando(false)
    }
  }

  async function handleFinalizar(confirmarParcial = false) {
    if (!apontamento) return
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await finalizarApontamento(apontamento.id, {
        fichas: apontamento.fichas.map(f => ({
          ficha_id:      f.id,
          qtd_produzida: parseInt(qtdsFichas[f.id] ?? '0', 10),
        })),
        confirmarParcial,
      })
      limparOverride(ap.id)
      upsertApontamento(ap)
    } catch (err) {
      type Resp409 = { requiresConfirmation?: boolean; totalBipado?: number; qtdeTotal?: number }
      const resp = (err as { response?: { status?: number; data?: Resp409 } })?.response
      if (!confirmarParcial && resp?.status === 409 && resp?.data?.requiresConfirmation) {
        setDadosFinalizacaoParcial({
          totalBipado: resp.data.totalBipado ?? totalBipado,
          qtdeTotal:   resp.data.qtdeTotal ?? qtdeTotal,
        })
        setShowConfirmarFinalizarParcial1(true)
      } else {
        setErroApi(apiMsg(err))
      }
    } finally {
      setAtualizando(false)
    }
  }

  function handleConfirmarFinalizarParcial1() {
    setShowConfirmarFinalizarParcial1(false)
    setShowConfirmarFinalizarParcial2(true)
  }

  function handleConfirmarFinalizarParcial2() {
    setShowConfirmarFinalizarParcial2(false)
    setDadosFinalizacaoParcial(null)
    void handleFinalizar(true)
  }

  async function handleFinalizarSemProducao() {
    if (!apontamento) return
    if (!confirm('Finalizar este lote sem bipar fichas individualmente?')) return
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await finalizarApontamentoSemProducao(apontamento.id)
      upsertApontamento(ap)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setAtualizando(false)
    }
  }

  async function handlePausar(motivoId: number) {
    if (!apontamento) return
    setPausando(true); setErroApi(null)
    try {
      const ap = await pausarApontamento(apontamento.id, motivoId)
      upsertApontamento(ap)
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
      upsertApontamento(ap)
      setSaiuSemPausar(false)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setRetomando(false)
    }
  }

  async function handlePausarOciosa(motivoId: number) {
    setPausandoOciosa(true); setErroApi(null)
    try {
      const s = await pausarSessaoOciosa(motivoId)
      setSessao(s)
      setShowModalPausaOciosa(false)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setPausandoOciosa(false)
    }
  }

  async function handleRetomarOciosa() {
    setRetomandoOciosa(true); setErroApi(null)
    try {
      const s = await retomarSessaoOciosa()
      setSessao(s)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setRetomandoOciosa(false)
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

  async function novoLote() {
    const finalizado = apontamento
    if (finalizado) {
      const restantes = apontamentos.filter(a => a.id !== finalizado.id)
      removerApontamento(finalizado.id)
      setQtdsFichas(prev => {
        const next = { ...prev }
        finalizado.fichas.forEach(f => { delete next[f.id] })
        return next
      })
      setFocoId(restantes[0]?.id ?? null)
    }
    setErroApi(null)
    setBarcode('')
    setSaiuSemPausar(false)
    await recarregarFichasRecentes()
  }

  async function handleSegundaPassagem() {
    if (!apontamento) return
    const idAntigo = apontamento.id
    const fichasAntigas = apontamento.fichas
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await segundaPassagem({ cod_peca: apontamento.cod_peca, ordem_lote: apontamento.ordem_lote })
      removerApontamento(idAntigo)
      upsertApontamento(ap)
      setFocoId(ap.id)
      setQtdsFichas(prev => {
        const next = { ...prev }
        fichasAntigas.forEach(f => { delete next[f.id] })
        return next
      })
      setBarcode('')
      setSaiuSemPausar(false)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setAtualizando(false)
    }
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

  const podeEncerrar = apontamentos.every(a => derivarFase(a) === 'concluido')
  const acoesSessaoDesabilitadas = atualizando || pausando || retomando || encerrando || finalizandoTurno || pausandoSessao || cancelando || pausandoOciosa || retomandoOciosa

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
            title="Pausa a sessão; ao retomar, o setup precisará ser refeito"
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

      {/* Tira de seleção — só aparece quando o lote tem mais de uma peça em andamento */}
      {apontamentos.length > 1 && (
        <div className="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
          {apontamentos.map(a => {
            const statusInfo = STATUS_LABEL[a.status]
            const focado = a.id === focoId
            return (
              <button
                key={a.id}
                type="button"
                onClick={() => setFocoId(a.id)}
                className={`shrink-0 flex flex-col items-start gap-0.5 px-4 py-2.5 rounded-xl border text-left transition-colors min-w-[150px] ${
                  focado
                    ? 'bg-[#00aa84]/10 border-[#00aa84]/40'
                    : 'bg-white/[0.03] border-white/5 hover:bg-white/[0.06]'
                }`}
              >
                <span className={`text-xs font-semibold truncate max-w-[170px] ${focado ? 'text-white' : 'text-slate-300'}`}>
                  {a.desc_peca ?? a.cod_peca}
                </span>
                <span className="flex items-center gap-1.5 text-[11px] text-slate-500">
                  lote {a.ordem_lote.replace(/^0+/, '')}
                  {statusInfo && <span className={`font-medium ${statusInfo.color}`}>· {statusInfo.label}</span>}
                </span>
              </button>
            )
          })}
        </div>
      )}

      {/* FASE: aguardando */}
      {fase === 'aguardando' && !sessao?.pausa_ociosa && (
        <>
          <BarcodeCard
            titulo="Bipar lote"
            subtitulo="Leia o código de barras do lote para iniciar o setup"
            barcode={barcode}
            barcodeOk={barcodeOk}
            inputRef={barcodeRef}
            atualizando={atualizando}
            botaoLabel="Iniciar Setup"
            botaoIcone={<Settings className="w-4 h-4" />}
            onChange={setBarcode}
            onSubmit={handleBiparLote}
          />
          <BotaoPausar
            label="Pausar"
            disabled={atualizando}
            onClick={() => setShowModalPausaOciosa(true)}
          />
          <FichasRecentes fichas={fichasRecentes} />
        </>
      )}

      {/* FASE: aguardando, sessão ociosa pausada */}
      {fase === 'aguardando' && sessao?.pausa_ociosa && (
        <div className="bg-[#0f1923] border border-amber-500/30 rounded-xl overflow-hidden">
          <div className="flex items-center gap-3 px-6 pt-5 pb-4 border-b border-amber-500/10">
            <div className="p-2 rounded-lg bg-amber-500/10">
              <Pause className="w-5 h-5 text-amber-400" />
            </div>
            <div>
              <p className="text-sm font-semibold text-white">Sessão pausada</p>
              <p className="text-xs text-slate-500 mt-0.5">
                Desde {fmtHoraDate(new Date(sessao.pausa_ociosa.inicio))}
                {sessao.pausa_ociosa.motivo && ` · ${sessao.pausa_ociosa.motivo}`}
              </p>
            </div>
          </div>
          <div className="px-6 py-5">
            <button
              type="button"
              onClick={handleRetomarOciosa}
              disabled={retomandoOciosa}
              className="w-full py-3 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed rounded-xl transition-colors flex items-center justify-center gap-2"
            >
              {retomandoOciosa
                ? <><Loader2 className="w-4 h-4 animate-spin" />Retomando…</>
                : <><Play className="w-4 h-4" />Retomar</>}
            </button>
          </div>
        </div>
      )}

      {/* FASE: em_setup */}
      {fase === 'em_setup' && apontamento && (
        <>
          {retomouAposPausaSessao && (
            <div className="flex items-start gap-2 bg-amber-500/10 border border-amber-500/20 rounded-xl px-4 py-3">
              <AlertCircle className="w-4 h-4 text-amber-400 mt-0.5 shrink-0" />
              <p className="text-xs text-amber-400">
                Sessão foi pausada — refaça o setup antes de continuar.
              </p>
            </div>
          )}
          {fichaSetup && (
            <div className="grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setAbaSetup('setup')}
                className={`flex items-center justify-center gap-2 h-16 rounded-xl text-sm font-semibold transition-colors ${
                  abaSetup === 'setup'
                    ? 'bg-amber-500/10 border border-amber-500/30 text-amber-400'
                    : 'bg-white/5 border border-white/5 text-slate-400 hover:bg-white/10'
                }`}
              >
                <Settings className="w-4 h-4" />
                Setup
              </button>
              <button
                type="button"
                onClick={() => setAbaSetup('ficha_setup')}
                className={`flex items-center justify-center gap-2 h-16 rounded-xl text-sm font-semibold transition-colors ${
                  abaSetup === 'ficha_setup'
                    ? 'bg-[#00aa84]/10 border border-[#00aa84]/30 text-[#00aa84]'
                    : 'bg-white/5 border border-white/5 text-slate-400 hover:bg-white/10'
                }`}
              >
                <ClipboardList className="w-4 h-4" />
                Ficha de Setup
              </button>
            </div>
          )}
          {abaSetup === 'ficha_setup' && fichaSetup ? (
            <FichaSetupPanel ficha={fichaSetup} />
          ) : (
            <FaseTimer
              titulo="Setup em andamento"
              subtitulo="Configure a máquina para iniciar a produção"
              icone={<Settings className="w-5 h-5 text-amber-400" />}
              corIcone="bg-amber-500/10"
              timer={timerSetup}
              corTimer="text-amber-400"
              produto={apontamento.desc_peca}
              codPeca={apontamento.cod_peca}
              ordemLote={apontamento.ordem_lote}
              qtdeTotal={apontamento.qtde_total}
              botaoLabel="Finalizar Setup"
              botaoIcone={<Play className="w-4 h-4" />}
              loading={atualizando}
              onAcao={handleFinalizarSetup}
            />
          )}
          <BotaoPausar
            label="Pausar Setup"
            disabled={atualizando}
            onClick={() => setShowModalPausa(true)}
          />
        </>
      )}

      {/* FASE: em_pausa_setup */}
      {fase === 'em_pausa_setup' && apontamento && (
        <PausadoPanel
          apontamento={apontamento}
          pausaAtual={pausaAtual}
          saiuSemPausar={saiuSemPausar}
          retomando={retomando}
          onRetomar={handleRetomar}
        />
      )}

      {/* FASE: aguardando_ficha */}
      {fase === 'aguardando_ficha' && apontamento && (
        <>
          <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
            <div className="flex items-center gap-3 px-6 pt-5 pb-4 border-b border-white/5">
              <div className="p-2 rounded-lg bg-[#00aa84]/10">
                <ScanLine className="w-5 h-5 text-[#00aa84]" />
              </div>
              <div>
                <p className="text-sm font-semibold text-white">Setup concluído — bipe a primeira ficha</p>
                <p className="text-xs text-slate-500 mt-0.5">
                  {apontamento.cod_peca} · lote {apontamento.ordem_lote.replace(/^0+/, '')}
                  {apontamento.qtde_total !== null && ` · ${apontamento.qtde_total} pç total`}
                </p>
              </div>
            </div>
            <div className="px-6 py-5">
              <BarcodeInline
                barcode={barcode}
                barcodeOk={barcodeOk}
                inputRef={barcodeRef}
                atualizando={atualizando}
                botaoLabel="Bipar ficha"
                onChange={setBarcode}
                onSubmit={handleBiparFicha}
              />
            </div>
          </div>
          {sessao?.maquina.regra_maquina?.possui_producao === false && (
            <button
              type="button"
              onClick={handleFinalizarSemProducao}
              disabled={atualizando}
              className="w-full py-3 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed rounded-xl transition-colors flex items-center justify-center gap-2"
            >
              {atualizando
                ? <><Loader2 className="w-4 h-4 animate-spin" />Finalizando…</>
                : <><CheckCircle2 className="w-4 h-4" />Finalizar sem bipar fichas</>}
            </button>
          )}
          <BotaoPausar
            label="Pausar"
            disabled={atualizando}
            onClick={() => setShowModalPausa(true)}
          />
          <FichasRecentes fichas={fichasRecentes} />
        </>
      )}

      {/* FASE: em_pausa_aguardando */}
      {fase === 'em_pausa_aguardando' && apontamento && (
        <PausadoPanel
          apontamento={apontamento}
          pausaAtual={pausaAtual}
          saiuSemPausar={saiuSemPausar}
          retomando={retomando}
          onRetomar={handleRetomar}
        />
      )}

      {/* FASE: em_producao */}
      {fase === 'em_producao' && apontamento && (
        <>
          {/* Card de info compacto: timer + dados do lote em uma linha */}
          <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
            <div className="flex items-center justify-between gap-3 px-5 py-3 border-b border-white/5">
              <div className="flex items-center gap-2 min-w-0">
                <div className="p-1.5 rounded-lg bg-[#00aa84]/10 shrink-0">
                  <Timer className="w-4 h-4 text-[#00aa84]" />
                </div>
                <div className="min-w-0">
                  <p className="text-xs font-semibold text-white truncate">
                    {apontamento.desc_peca ?? apontamento.cod_peca}
                  </p>
                  <p className="text-xs text-slate-500">
                    lote {apontamento.ordem_lote.replace(/^0+/, '')}
                    {apontamento.qtde_total !== null && (
                      <span className="ml-2 text-[#00aa84] font-medium">{totalBipado}/{apontamento.qtde_total} pç</span>
                    )}
                  </p>
                </div>
              </div>
              <div className="shrink-0 text-right">
                <p className="text-xs text-slate-500 leading-none mb-0.5">produção</p>
                <p className="text-2xl font-mono font-bold tabular-nums text-[#00aa84]">{timerProducao}</p>
              </div>
            </div>
          </div>

          {/* Scan — sempre visível */}
          <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
            <div className="flex items-center gap-2 px-5 py-3 border-b border-white/5">
              <QrCode className="w-4 h-4 text-slate-400" />
              <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Bipar próxima ficha</p>
            </div>
            <div className="px-5 py-4">
              <BarcodeInline
                barcode={barcode}
                barcodeOk={barcodeOk}
                inputRef={barcodeRef}
                atualizando={atualizando}
                botaoLabel="Confirmar"
                onChange={setBarcode}
                onSubmit={handleBiparFicha}
              />
            </div>
          </div>

          {/* Botões de ação — sempre visíveis, antes das fichas */}
          <div className="space-y-3">
            <button
              type="button"
              onClick={() => focarFinalizando(apontamento.id)}
              disabled={atualizando}
              className="w-full py-3 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-40 disabled:cursor-not-allowed rounded-xl transition-colors flex items-center justify-center gap-2"
            >
              <CheckCircle2 className="w-4 h-4" />
              {loteZerado ? 'Finalizar Produção' : 'Finalizar Produção (parcial)'}
            </button>
            {!loteZerado && qtdeTotal > 0 && (
              <p className="text-xs text-center text-slate-500">
                {totalBipado} de {qtdeTotal} peças bipadas
                {!todasCoresCompletas && ' · faltam cores'}
              </p>
            )}
            <BotaoPausar
              label="Pausar Produção"
              disabled={atualizando}
              onClick={() => setShowModalPausa(true)}
            />
          </div>

          {/* Fichas após os botões — scrollável internamente */}
          {apontamento.fichas.length > 0 && (
            <FichasDoLote fichas={apontamento.fichas} resumoPorCor={resumoPorCor} />
          )}

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
          {apontamento.fichas.length > 0 && (
            <FichasDoLote fichas={apontamento.fichas} resumoPorCor={resumoPorCor} />
          )}
        </>
      )}

      {/* FASE: finalizando */}
      {fase === 'finalizando' && apontamento && (
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
              {apontamento.fichas.map(f => {
                const totalPilhas = f.total_pilhas
                return (
                  <div key={f.id} className="bg-white/[0.03] border border-white/5 rounded-lg px-4 py-3 space-y-2">
                    <div className="flex items-center justify-between">
                      <div>
                        <span className="text-xs font-mono font-semibold text-white">{f.cod_peca}</span>
                        <span className="ml-2 text-xs text-slate-500">
                          pilha {f.pilha}{totalPilhas > 0 ? ` / ${totalPilhas}` : ''}
                        </span>
                      </div>
                      <span className="text-xs text-slate-500">{f.qtd_peca} pç/ficha</span>
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
                )
              })}
            </div>
            <div className="flex gap-3 pt-1">
              <button
                type="button"
                onClick={() => limparOverride(apontamento.id)}
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
              <p className="text-xs text-slate-500 mt-0.5">
                {apontamento.desc_peca ?? apontamento.cod_peca} · lote {apontamento.ordem_lote.replace(/^0+/, '')}
              </p>
            </div>
          </div>
          <div className="px-6 py-5 space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <InfoCard
                label="Tempo setup"
                value={formatDuracao(apontamento.setup_duracao_segundos ?? null)}
              />
              <InfoCard
                label="Tempo produção"
                value={formatDuracao(apontamento.producao_duracao_segundos ?? null)}
              />
            </div>
            {apontamento.fichas.length > 0 && (
              <div className="space-y-2">
                <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Fichas produzidas</p>
                <div className="divide-y divide-white/5 bg-white/[0.02] rounded-lg overflow-hidden">
                  <div className="grid grid-cols-3 px-4 py-1.5">
                    <span className="text-[10px] font-semibold text-slate-600 uppercase tracking-wider">Pilha</span>
                    <span className="text-[10px] font-semibold text-slate-600 uppercase tracking-wider text-center">Tempo</span>
                    <span className="text-[10px] font-semibold text-slate-600 uppercase tracking-wider text-right">Produzido</span>
                  </div>
                  {apontamento.fichas.map(f => (
                    <div key={f.id} className="grid grid-cols-3 items-center px-4 py-2.5">
                      <span className="text-xs font-mono text-white">
                        {f.cod_peca}
                        <span className="block text-slate-500">pilha {f.pilha}</span>
                      </span>
                      <span className="text-xs font-mono text-slate-400 text-center">
                        {formatDuracao(f.duracao_segundos)}
                      </span>
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
              onClick={() => setShowConfirmarSegundaPassagem(true)}
              disabled={atualizando}
              className="w-full py-2.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              {atualizando
                ? <><Loader2 className="w-4 h-4 animate-spin" />Iniciando…</>
                : <><RotateCcw className="w-4 h-4" />Passar novamente nesta máquina</>}
            </button>
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

      {/* Modal de confirmação fim de turno */}
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
                className="flex-1 py-2.5 text-sm font-semibold text-white bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                {finalizandoTurno
                  ? <><Loader2 className="w-4 h-4 animate-spin" />Finalizando…</>
                  : 'Finalizar Turno'}
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

      {/* Modal de motivo de pausa ociosa (sem apontamento) */}
      {showModalPausaOciosa && (
        <MotivoPausaModal
          motivos={motivosPausa}
          pausando={pausandoOciosa}
          onSelect={handlePausarOciosa}
          onClose={() => setShowModalPausaOciosa(false)}
        />
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

      {/* Modal de confirmação — passar novamente nesta máquina */}
      {showConfirmarSegundaPassagem && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <div className="flex items-center gap-2 px-5 py-4 border-b border-white/5">
              <RotateCcw className="w-4 h-4 text-amber-400" />
              <p className="text-sm font-semibold text-white">Passar novamente</p>
            </div>
            <div className="p-5 space-y-4">
              <p className="text-sm text-slate-400 leading-relaxed">
                Deseja iniciar uma nova passagem do mesmo lote nesta máquina?
              </p>
              <div className="flex gap-3">
                <button
                  onClick={() => setShowConfirmarSegundaPassagem(false)}
                  className="flex-1 px-4 py-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/5 text-sm font-medium text-slate-300 transition-all"
                >
                  Cancelar
                </button>
                <button
                  onClick={() => { setShowConfirmarSegundaPassagem(false); handleSegundaPassagem() }}
                  disabled={atualizando}
                  className="flex-1 px-4 py-3 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/20 text-sm font-medium text-amber-300 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                >
                  {atualizando ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <RotateCcw className="w-3.5 h-3.5" />}
                  Confirmar
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal de confirmação de nova passagem */}
      {showConfirmarNovaPassagem && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <div className="flex items-center gap-2 px-5 py-4 border-b border-white/5">
              <RotateCcw className="w-4 h-4 text-blue-400" />
              <p className="text-sm font-semibold text-white">Nova passagem</p>
            </div>
            <div className="p-5 space-y-4">
              <p className="text-sm text-slate-400 leading-relaxed">
                Todas as pilhas deste lote já foram processadas. Deseja iniciar uma nova passagem?
              </p>
              <div className="flex gap-3">
                <button
                  onClick={() => { setShowConfirmarNovaPassagem(false); setDadosNovaPassagem(null); setBarcode('') }}
                  className="flex-1 px-4 py-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/5 text-sm font-medium text-slate-300 transition-all"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleConfirmarNovaPassagem}
                  disabled={atualizando}
                  className="flex-1 px-4 py-3 rounded-xl bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/20 text-sm font-medium text-blue-300 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                >
                  {atualizando ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <RotateCcw className="w-3.5 h-3.5" />}
                  Iniciar
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal 1/2 de confirmação — finalizar com peças/cores faltando */}
      {showConfirmarFinalizarParcial1 && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <div className="flex items-center gap-2 px-5 py-4 border-b border-white/5">
              <AlertCircle className="w-4 h-4 text-amber-400" />
              <p className="text-sm font-semibold text-white">Finalizar faltando peças?</p>
            </div>
            <div className="p-5 space-y-4">
              <p className="text-sm text-slate-400 leading-relaxed">
                {dadosFinalizacaoParcial
                  ? `Foram bipadas ${dadosFinalizacaoParcial.totalBipado} de ${dadosFinalizacaoParcial.qtdeTotal} peças. Deseja finalizar mesmo assim, deixando o restante para um próximo apontamento?`
                  : 'Ainda faltam peças ou cores para completar este lote. Deseja finalizar mesmo assim?'}
              </p>
              <div className="flex gap-3">
                <button
                  onClick={() => { setShowConfirmarFinalizarParcial1(false); setDadosFinalizacaoParcial(null) }}
                  className="flex-1 px-4 py-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/5 text-sm font-medium text-slate-300 transition-all"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleConfirmarFinalizarParcial1}
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

      {/* Modal 2/2 de confirmação — reforço antes de finalizar parcial */}
      {showConfirmarFinalizarParcial2 && (
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
                  onClick={() => { setShowConfirmarFinalizarParcial2(false); setDadosFinalizacaoParcial(null) }}
                  className="flex-1 px-4 py-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/5 text-sm font-medium text-slate-300 transition-all"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleConfirmarFinalizarParcial2}
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
