import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import axios from 'axios'
import { ArrowLeft, ClipboardList, Loader2, Save } from 'lucide-react'
import { getMaquinas, type Maquina } from '@/api/maquinas'
import { getOperarios, type Operario } from '@/api/operarios'
import { getBrocas, type Broca } from '@/api/brocas'
import {
  createFichaCabecote,
  updateFichaCabecote,
  getFichaCabecote,
  type CreateFichaCabecotePayload,
  type SentidoCabecote,
} from '@/api/fichasCabecote'
import {
  CabecotePosicoesTable,
  novaCabecotePosicaoRow,
  type CabecotePosicaoRow,
} from '@/components/fichaCabecote/CabecotePosicoesTable'
import {
  CabecoteBrocasTable,
  novaCabecoteBrocaRow,
  type CabecoteBrocaRow,
} from '@/components/fichaCabecote/CabecoteBrocasTable'

function parseError(err: unknown, fallback: string): string {
  if (axios.isAxiosError(err)) {
    if (err.response?.data?.errors) {
      const msgs = Object.values(err.response.data.errors as Record<string, string[]>)
        .flat()
        .join(' ')
      if (msgs) return msgs
    }
    if (err.response?.data?.message) {
      return err.response.data.message
    }
  }
  return fallback
}

function numeroOuNulo(valor: string): number | null {
  return valor.trim() === '' ? null : Number(valor)
}

function linhaCabecotePreenchida(r: CabecotePosicaoRow): boolean {
  return !!(r.cabecote || r.sentido || r.largura_mm || r.deslocamento_mm || r.altura_cabecote_mm || r.obs)
}

function linhaCabecoteValida(r: CabecotePosicaoRow): boolean {
  return !!(r.cabecote && r.sentido && r.largura_mm && r.deslocamento_mm && r.altura_cabecote_mm)
}

function linhaBrocaPreenchida(r: CabecoteBrocaRow): boolean {
  return !!(r.cabecote || r.sentido || r.posicao || r.broca_id || r.profundidade_mm || r.agregado || r.obs)
}

function linhaBrocaValida(r: CabecoteBrocaRow): boolean {
  return !!(r.cabecote && r.sentido && r.posicao && r.broca_id && (r.passante || r.profundidade_mm))
}

const INPUT = 'w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors'
const SELECT = 'w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors'
const LABEL = 'block text-xs font-medium text-slate-400 mb-1.5'

export function FichaCabecoteFormPage() {
  const navigate = useNavigate()
  const { produtoId, pecaId, fichaId } = useParams<{ produtoId: string; pecaId: string; fichaId?: string }>()

  const [fichaIdAtual, setFichaIdAtual] = useState<number | null>(fichaId ? Number(fichaId) : null)

  const [maquinas, setMaquinas] = useState<Maquina[]>([])
  const [operarios, setOperarios] = useState<Operario[]>([])
  const [brocas, setBrocas] = useState<Broca[]>([])
  const [loadingOpcoes, setLoadingOpcoes] = useState(true)

  const [maquinaId, setMaquinaId] = useState('')
  const [operarioId, setOperarioId] = useState('')
  const [data, setData] = useState('')
  const [topEsquerdo, setTopEsquerdo] = useState('')
  const [topDireito, setTopDireito] = useState('')
  const [quantidadePecas, setQuantidadePecas] = useState('')
  const [velocidade, setVelocidade] = useState('')
  const [observacao, setObservacao] = useState('')

  const [posicoesCabecote, setPosicoesCabecote] = useState<CabecotePosicaoRow[]>([novaCabecotePosicaoRow()])
  const [posicoesBroca, setPosicoesBroca] = useState<CabecoteBrocaRow[]>([novaCabecoteBrocaRow()])

  const [salvando, setSalvando] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [sucesso, setSucesso] = useState(false)

  useEffect(() => {
    const controller = new AbortController()
    Promise.all([
      getMaquinas(controller.signal),
      getOperarios(controller.signal),
      getBrocas(controller.signal),
      fichaIdAtual ? getFichaCabecote(fichaIdAtual, controller.signal) : Promise.resolve(null),
    ])
      .then(([m, o, b, ficha]) => {
        setMaquinas(m.filter(maq => maq.ativa && maq.etapa_fluxo?.requer_config_cabecote))
        setOperarios(o)
        setBrocas(b.filter(broca => broca.ativo))

        if (ficha) {
          setMaquinaId(ficha.maquina_id !== null ? String(ficha.maquina_id) : '')
          setOperarioId(ficha.operario_id !== null ? String(ficha.operario_id) : '')
          setData(ficha.data ?? '')
          setTopEsquerdo(ficha.top_esquerdo_mm !== null ? String(ficha.top_esquerdo_mm) : '')
          setTopDireito(ficha.top_direito_mm !== null ? String(ficha.top_direito_mm) : '')
          setQuantidadePecas(ficha.quantidade_pecas_vez !== null ? String(ficha.quantidade_pecas_vez) : '')
          setVelocidade(ficha.velocidade_trabalho !== null ? String(ficha.velocidade_trabalho) : '')
          setObservacao(ficha.observacao ?? '')

          if (ficha.posicoes_cabecote.length > 0) {
            setPosicoesCabecote(ficha.posicoes_cabecote.map(p => ({
              key: crypto.randomUUID(),
              cabecote: p.cabecote,
              sentido: p.sentido,
              largura_mm: String(p.largura_mm),
              deslocamento_mm: String(p.deslocamento_mm),
              altura_cabecote_mm: String(p.altura_cabecote_mm),
              obs: p.obs ?? '',
            })))
          }

          if (ficha.posicoes_broca.length > 0) {
            setPosicoesBroca(ficha.posicoes_broca.map(b => ({
              key: crypto.randomUUID(),
              cabecote: b.cabecote,
              sentido: b.sentido,
              posicao: b.posicao,
              broca_id: String(b.broca_id),
              passante: b.passante,
              profundidade_mm: b.profundidade_mm !== null ? String(b.profundidade_mm) : '',
              agregado: b.agregado ?? '',
              obs: b.obs ?? '',
            })))
          }
        }
      })
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar máquinas, operadores, brocas ou a ficha.')
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoadingOpcoes(false)
      })
    return () => controller.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const linhasCabecoteInvalidas = posicoesCabecote.some(r => linhaCabecotePreenchida(r) && !linhaCabecoteValida(r))
  const linhasBrocaInvalidas = posicoesBroca.some(r => linhaBrocaPreenchida(r) && !linhaBrocaValida(r))
  const podeSalvar = !salvando && !linhasCabecoteInvalidas && !linhasBrocaInvalidas

  async function handleSalvar() {
    if (!pecaId || !podeSalvar) return
    setError(null)
    setSucesso(false)
    setSalvando(true)
    try {
      const payload: CreateFichaCabecotePayload = {
        maquina_id: maquinaId ? Number(maquinaId) : null,
        operario_id: operarioId ? Number(operarioId) : null,
        data: data || null,
        top_esquerdo_mm: numeroOuNulo(topEsquerdo),
        top_direito_mm: numeroOuNulo(topDireito),
        quantidade_pecas_vez: numeroOuNulo(quantidadePecas),
        velocidade_trabalho: numeroOuNulo(velocidade),
        observacao: observacao.trim() || null,
        posicoes_cabecote: posicoesCabecote
          .filter(linhaCabecoteValida)
          .map(r => ({
            cabecote: r.cabecote,
            sentido: r.sentido as SentidoCabecote,
            largura_mm: Number(r.largura_mm),
            deslocamento_mm: Number(r.deslocamento_mm),
            altura_cabecote_mm: Number(r.altura_cabecote_mm),
            obs: r.obs.trim() || null,
          })),
        posicoes_broca: posicoesBroca
          .filter(linhaBrocaValida)
          .map(r => ({
            cabecote: r.cabecote,
            sentido: r.sentido as SentidoCabecote,
            posicao: r.posicao,
            broca_id: Number(r.broca_id),
            passante: r.passante,
            profundidade_mm: r.passante ? null : Number(r.profundidade_mm),
            agregado: r.agregado.trim() || null,
            obs: r.obs.trim() || null,
          })),
      }

      if (fichaIdAtual) {
        await updateFichaCabecote(fichaIdAtual, payload)
      } else {
        const nova = await createFichaCabecote(Number(pecaId), payload)
        setFichaIdAtual(nova.id)
        navigate(`/admin/produtos/${produtoId}/semi-acabados/${pecaId}/fichas/${nova.id}/editar`, { replace: true })
      }
      setSucesso(true)
    } catch (err: unknown) {
      setError(parseError(err, 'Não foi possível salvar a ficha.'))
    } finally {
      setSalvando(false)
    }
  }

  return (
    <div className="space-y-6">

      {/* cabeçalho */}
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={() => navigate(`/admin/produtos/${produtoId}/semi-acabados/${pecaId}/fichas`)}
          className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
          title="Voltar"
        >
          <ArrowLeft className="w-5 h-5" />
        </button>
        <div className="p-2 rounded-lg bg-[#00aa84]/10">
          <ClipboardList className="w-5 h-5 text-[#00aa84]" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-white">
            {fichaIdAtual ? 'Editar Ficha de Cabeçote' : 'Nova Ficha de Cabeçote'}
          </h1>
          <p className="text-sm text-slate-400">
            Preencha o que tiver disponível — é possível salvar a qualquer momento e continuar depois
          </p>
        </div>
      </div>

      {loadingOpcoes ? (
        <div className="flex items-center justify-center gap-2 py-16 text-slate-400">
          <Loader2 className="w-5 h-5 animate-spin" />
          <span className="text-sm">Carregando…</span>
        </div>
      ) : (
        <>
          {/* Identificação */}
          <section className="bg-[#0f1923] border border-white/5 rounded-xl px-6 py-5 space-y-4">
            <h2 className="text-xs font-medium text-slate-400 uppercase tracking-wider">Identificação</h2>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label className={LABEL}>Máquina</label>
                <select value={maquinaId} onChange={e => setMaquinaId(e.target.value)} className={SELECT}>
                  <option value="">Selecione</option>
                  {maquinas.map(m => <option key={m.id} value={m.id}>{m.nome}</option>)}
                </select>
              </div>
              <div>
                <label className={LABEL}>Operador</label>
                <select value={operarioId} onChange={e => setOperarioId(e.target.value)} className={SELECT}>
                  <option value="">Selecione</option>
                  {operarios.map(o => <option key={o.id} value={o.id}>{o.user.name}</option>)}
                </select>
              </div>
              <div>
                <label className={LABEL}>Data</label>
                <input type="date" value={data} onChange={e => setData(e.target.value)} className={INPUT} />
              </div>
              <div>
                <label className={LABEL}>Quant. Peças/vez</label>
                <input type="number" min="1" value={quantidadePecas} onChange={e => setQuantidadePecas(e.target.value)} className={INPUT} />
              </div>
              <div>
                <label className={LABEL}>Top Esquerdo (mm)</label>
                <input type="number" step="0.01" value={topEsquerdo} onChange={e => setTopEsquerdo(e.target.value)} className={INPUT} />
              </div>
              <div>
                <label className={LABEL}>Top Direito (mm)</label>
                <input type="number" step="0.01" value={topDireito} onChange={e => setTopDireito(e.target.value)} className={INPUT} />
              </div>
              <div>
                <label className={LABEL}>Velocidade de Trabalho</label>
                <input type="number" step="0.01" value={velocidade} onChange={e => setVelocidade(e.target.value)} className={INPUT} />
              </div>
              <div className="md:col-span-4">
                <label className={LABEL}>Observação</label>
                <textarea value={observacao} onChange={e => setObservacao(e.target.value)} rows={2} className={INPUT} />
              </div>
            </div>
          </section>

          {/* Levantamento de Posições de Cabeçotes */}
          <section className="bg-[#0f1923] border border-white/5 rounded-xl px-6 py-5 space-y-4">
            <h2 className="text-xs font-medium text-slate-400 uppercase tracking-wider">Levantamento de Posições de Cabeçotes</h2>
            <CabecotePosicoesTable rows={posicoesCabecote} onChange={setPosicoesCabecote} />
          </section>

          {/* Posição das Brocas */}
          <section className="bg-[#0f1923] border border-white/5 rounded-xl px-6 py-5 space-y-4">
            <h2 className="text-xs font-medium text-slate-400 uppercase tracking-wider">Posição das Brocas</h2>
            <CabecoteBrocasTable rows={posicoesBroca} onChange={setPosicoesBroca} brocas={brocas} />
          </section>

          {error && (
            <p className="text-xs text-red-400 bg-red-400/10 border border-red-400/20 rounded-lg px-3 py-2">
              {error}
            </p>
          )}
          {sucesso && !error && (
            <p className="text-xs text-[#00aa84] bg-[#00aa84]/10 border border-[#00aa84]/20 rounded-lg px-3 py-2">
              Ficha salva. Você pode continuar preenchendo e salvar novamente quando quiser.
            </p>
          )}

          <div className="flex justify-end">
            <button
              type="button"
              onClick={handleSalvar}
              disabled={!podeSalvar}
              title={linhasCabecoteInvalidas || linhasBrocaInvalidas ? 'Complete ou limpe as linhas iniciadas antes de salvar' : undefined}
              className="flex items-center gap-2 px-6 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
            >
              {salvando ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
              Salvar Ficha
            </button>
          </div>
        </>
      )}
    </div>
  )
}
