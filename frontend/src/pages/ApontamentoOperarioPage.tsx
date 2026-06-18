import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Loader2, LogOut, CheckCircle2, RotateCcw,
  AlertCircle, Cpu, ScanLine, Settings, Play,
  Timer, PackageCheck, QrCode, Flag,
} from 'lucide-react'
import { getSessaoAtiva, getTurnoHoje, encerrarSessao, encerrarTurno, type Sessao, type TurnoHoje } from '@/api/sessao'
import {
  getApontamentoAtivo,
  biparLote,
  finalizarSetup,
  biparFicha,
  finalizarApontamento,
  getFichasRecentes,
  pausarApontamento,
  retomarApontamento,
  pausarSistemaBeacon,
  type Apontamento,
  type FichaApontamento,
} from '@/api/apontamento'
import { getMotivosAtivos, type MotivoPausa } from '@/api/motivosPausa'
import { FichasRecentes } from '@/components/FichasRecentes'
import { BarcodeCard } from '@/components/apontamento/BarcodeCard'
import { BarcodeInline } from '@/components/apontamento/BarcodeInline'
import { BotaoPausar } from '@/components/apontamento/BotaoPausar'
import { FaseTimer } from '@/components/apontamento/FaseTimer'
import { FichasDoLote } from '@/components/apontamento/FichasDoLote'
import { InfoCard } from '@/components/apontamento/InfoCard'
import { MotivoPausaModal } from '@/components/apontamento/MotivoPausaModal'
import { PausadoPanel } from '@/components/apontamento/PausadoPanel'
import { useTimerLiquido } from '@/hooks/useTimerLiquido'
import { parseBarcode, BARCODE_LENGTH } from '@/lib/barcode'
import { formatDuracao, derivarFase, apiMsg, mensagemFinalizarTurno, horarioLiberacaoTurno, fmtHoraDate, type Fase } from '@/lib/apontamentoFormat'

export function ApontamentoOperarioPage() {
  const navigate = useNavigate()

  const [sessao, setSessao]                 = useState<Sessao | null>(null)
  const [turnoHoje, setTurnoHoje]           = useState<TurnoHoje | null>(null)
  const [now, setNow]                       = useState(() => new Date())
  const [apontamento, setApontamento]       = useState<Apontamento | null>(null)
  const [fase, setFase]                     = useState<Fase>('aguardando')
  const [fichasRecentes, setFichasRecentes] = useState<FichaApontamento[]>([])
  const [motivosPausa, setMotivosPausa]     = useState<MotivoPausa[]>([])
  const [loadingInicial, setLoadingInicial] = useState(true)
  const [encerrando, setEncerrando]               = useState(false)
  const [finalizandoTurno, setFinalizandoTurno]   = useState(false)
  const [showModalTurno, setShowModalTurno]       = useState(false)
  const [atualizando, setAtualizando]       = useState(false)
  const [pausando, setPausando]             = useState(false)
  const [retomando, setRetomando]           = useState(false)
  const [erroApi, setErroApi]               = useState<string | null>(null)
  const [showModalPausa, setShowModalPausa] = useState(false)
  const [saiuSemPausar, setSaiuSemPausar]   = useState(false)
  const [barcode, setBarcode]               = useState('')
  const barcodeRef                          = useRef<HTMLInputElement>(null)
  const [qtdsFichas, setQtdsFichas]         = useState<Record<number, string>>({})

  const parsedBarcode = barcode.length === BARCODE_LENGTH ? parseBarcode(barcode) : null
  const barcodeOk     = parsedBarcode !== null

  const qtdeTotal   = apontamento?.qtde_total ?? 0
  const totalBipado = apontamento?.fichas.reduce((sum, f) => sum + f.qtd_peca, 0) ?? 0
  const loteZerado  = qtdeTotal === 0 || totalBipado >= qtdeTotal

  const pausas = useMemo(() => apontamento?.pausas ?? [], [apontamento])

  const setupInicio    = apontamento?.setup_fim    ? null : (apontamento?.setup_inicio    ?? null)
  const producaoInicio = apontamento?.producao_fim ? null : (apontamento?.producao_inicio ?? null)

  const timerSetup    = useTimerLiquido(setupInicio,    pausas, 'setup')
  const timerProducao = useTimerLiquido(producaoInicio, pausas, 'producao')

  const pausaAtual = useMemo(() => pausas.find(p => p.fim === null), [pausas])

  useEffect(() => {
    Promise.all([
      getSessaoAtiva(),
      getApontamentoAtivo(),
      getFichasRecentes(),
      getMotivosAtivos(),
      getTurnoHoje(),
    ]).then(([s, a, fr, mp, turno]) => {
      if (!s) { navigate('/operario', { replace: true }); return }
      setSessao(s)
      setFichasRecentes(fr)
      setMotivosPausa(mp)
      setTurnoHoje(turno)

      if (!a) { setFase('aguardando'); return }

      setApontamento(a)
      const f = derivarFase(a)
      setFase(f)

      if (f === 'em_pausa_setup' || f === 'em_pausa_producao') {
        const openPausa = a.pausas.find(p => p.fim === null)
        if (openPausa?.is_sistema) setSaiuSemPausar(true)
      }

      if (f === 'em_producao' || f === 'finalizando' || f === 'em_pausa_producao') {
        const init: Record<number, string> = {}
        a.fichas.forEach(fi => { init[fi.id] = String(fi.qtd_peca) })
        setQtdsFichas(init)
      }
    }).finally(() => setLoadingInicial(false))
  }, [navigate])

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
    if (!apontamento || (fase !== 'em_setup' && fase !== 'em_producao')) return
    const id = apontamento.id
    const handler = () => pausarSistemaBeacon(id)
    window.addEventListener('beforeunload', handler)
    return () => window.removeEventListener('beforeunload', handler)
  }, [apontamento?.id, fase]) // eslint-disable-line react-hooks/exhaustive-deps

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
      const ap = await biparLote({ cod_peca: parsedBarcode.cod_peca, ordem_lote: parsedBarcode.ordem_lote })
      setApontamento(ap)
      setBarcode('')
      setFase('em_setup')
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setAtualizando(false)
    }
  }

  async function handleFinalizarSetup() {
    if (!apontamento) return
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await finalizarSetup(apontamento.id)
      setApontamento(ap)
      setFase('aguardando_ficha')
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setAtualizando(false)
    }
  }

  async function handleBiparFicha() {
    if (!apontamento || !parsedBarcode) return
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await biparFicha(apontamento.id, {
        cod_peca:   parsedBarcode.cod_peca,
        ordem_lote: parsedBarcode.ordem_lote,
        qtd_peca:   parsedBarcode.qtd_peca,
        pilha:      parsedBarcode.pilha,
      })
      setApontamento(ap)
      setQtdsFichas(prev => ({
        ...prev,
        ...Object.fromEntries(
          ap.fichas
            .filter(f => !(f.id in prev))
            .map(f => [f.id, String(f.qtd_peca)])
        ),
      }))
      setBarcode('')
      setFase('em_producao')
      recarregarFichasRecentes()
    } catch (err) {
      setErroApi(apiMsg(err))
      setBarcode('')
    } finally {
      setAtualizando(false)
    }
  }

  async function handleFinalizar() {
    if (!apontamento) return
    setAtualizando(true); setErroApi(null)
    try {
      const ap = await finalizarApontamento(apontamento.id, {
        fichas: apontamento.fichas.map(f => ({
          ficha_id:      f.id,
          qtd_produzida: parseInt(qtdsFichas[f.id] ?? '0', 10),
        })),
      })
      setApontamento(ap)
      setFase('concluido')
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
      setApontamento(ap)
      setFase(derivarFase(ap))
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
      setFase(derivarFase(ap))
      setSaiuSemPausar(false)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setRetomando(false)
    }
  }

  async function novoLote() {
    setApontamento(null)
    setErroApi(null)
    setBarcode('')
    setQtdsFichas({})
    setSaiuSemPausar(false)
    await recarregarFichasRecentes()
    setFase('aguardando')
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

  const podeEncerrar = fase === 'aguardando' || fase === 'concluido'
  const acoesSessaoDesabilitadas = atualizando || pausando || retomando || encerrando || finalizandoTurno

  const horarioLiberacao   = turnoHoje ? horarioLiberacaoTurno(turnoHoje) : null
  const podeFinalizarTurno = !horarioLiberacao || now >= horarioLiberacao

  return (
    <div className="max-w-xl mx-auto space-y-5">

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
            onClick={handleEncerrar}
            disabled={encerrando || !podeEncerrar}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 bg-white/5 hover:bg-red-500/10 hover:text-red-400 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
          >
            {encerrando ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <LogOut className="w-3.5 h-3.5" />}
            Encerrar
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
          <FichasRecentes fichas={fichasRecentes} />
        </>
      )}

      {/* FASE: em_setup */}
      {fase === 'em_setup' && apontamento && (
        <>
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
          <FichasRecentes fichas={fichasRecentes} />
        </>
      )}

      {/* FASE: em_producao */}
      {fase === 'em_producao' && apontamento && (
        <>
          <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
            <div className="flex items-center gap-3 px-6 pt-5 pb-4 border-b border-white/5">
              <div className="p-2 rounded-lg bg-[#00aa84]/10">
                <Timer className="w-5 h-5 text-[#00aa84]" />
              </div>
              <div>
                <p className="text-sm font-semibold text-white">Produção em andamento</p>
                <p className="text-xs text-slate-500 mt-0.5">
                  {apontamento.cod_peca} · lote {apontamento.ordem_lote.replace(/^0+/, '')}
                </p>
              </div>
            </div>
            <div className="px-6 py-5 space-y-4">
              <div className="flex flex-col items-center py-2">
                <p className="text-xs text-slate-500 mb-1">Tempo de produção</p>
                <p className="text-4xl font-mono font-bold tabular-nums text-[#00aa84]">{timerProducao}</p>
              </div>
              <div className="grid grid-cols-2 gap-3">
                {apontamento.desc_peca && (
                  <div className="col-span-2 bg-white/[0.03] rounded-lg px-3 py-2.5">
                    <p className="text-xs text-slate-500">Produto</p>
                    <p className="text-sm font-semibold text-white mt-0.5">{apontamento.desc_peca}</p>
                  </div>
                )}
                {apontamento.qtde_total !== null && (
                  <div className="col-span-2 bg-white/[0.03] rounded-lg px-3 py-2.5 flex items-center justify-between">
                    <p className="text-xs text-slate-500">Total do pedido</p>
                    <p className="text-sm font-semibold text-[#00aa84]">{apontamento.qtde_total} pç</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {apontamento.fichas.length > 0 && (
            <FichasDoLote fichas={apontamento.fichas} qtdeTotal={apontamento.qtde_total} />
          )}

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

          <div className="space-y-3">
            <button
              type="button"
              onClick={() => setFase('finalizando')}
              disabled={atualizando || !loteZerado}
              className="w-full py-3 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-40 disabled:cursor-not-allowed rounded-xl transition-colors flex items-center justify-center gap-2"
            >
              <CheckCircle2 className="w-4 h-4" />
              {loteZerado ? 'Finalizar Produção' : 'Faltam peças para finalizar'}
            </button>
            {!loteZerado && qtdeTotal > 0 && (
              <p className="text-xs text-center text-slate-500">
                {totalBipado} de {qtdeTotal} peças bipadas
              </p>
            )}
            <BotaoPausar
              label="Pausar Produção"
              disabled={atualizando}
              onClick={() => setShowModalPausa(true)}
            />
          </div>

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
            <FichasDoLote fichas={apontamento.fichas} qtdeTotal={apontamento.qtde_total} />
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
                onClick={() => setFase('em_producao')}
                className="flex-1 py-2.5 text-sm font-medium text-slate-400 bg-white/5 hover:bg-white/10 rounded-lg transition-colors"
              >
                Voltar
              </button>
              <button
                type="button"
                onClick={handleFinalizar}
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

      {/* Modal de motivo de pausa */}
      {showModalPausa && (
        <MotivoPausaModal
          motivos={motivosPausa}
          pausando={pausando}
          onSelect={handlePausar}
          onClose={() => setShowModalPausa(false)}
        />
      )}
    </div>
  )
}
