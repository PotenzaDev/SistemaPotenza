import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import axios from 'axios'
import { ArrowLeft, ClipboardList, Loader2, Pencil } from 'lucide-react'
import { getFichaCabecote, type FichaCabecote } from '@/api/fichasCabecote'

const FIELD_LABEL = 'text-xs text-slate-500'
const FIELD_VALUE = 'text-sm text-white'
const TH = 'px-3 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider'
const TD = 'px-3 py-2 text-slate-300'

function formatDataBr(iso: string | null): string {
  if (!iso) return '—'
  const [y, m, d] = iso.slice(0, 10).split('-')
  return `${d}/${m}/${y}`
}

export function FichaCabecoteDetalhePage() {
  const navigate = useNavigate()
  const { produtoId, pecaId, fichaId } = useParams<{ produtoId: string; pecaId: string; fichaId: string }>()

  const [ficha, setFicha] = useState<FichaCabecote | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback((signal?: AbortSignal) => {
    if (!fichaId) return
    setLoading(true)
    setError(null)
    getFichaCabecote(Number(fichaId), signal)
      .then(setFicha)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar a ficha.')
      })
      .finally(() => {
        if (!signal?.aborted) setLoading(false)
      })
  }, [fichaId])

  useEffect(() => {
    const controller = new AbortController()
    load(controller.signal)
    return () => controller.abort()
  }, [load])

  return (
    <div className="space-y-6">

      {/* cabeçalho */}
      <div className="flex items-center justify-between">
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
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-semibold text-white">Ficha de Cabeçote</h1>
              {ficha && (
                <span className={`text-xs px-2 py-0.5 rounded-full ${ficha.completa ? 'bg-[#00aa84]/10 text-[#00aa84]' : 'bg-amber-400/10 text-amber-400'}`}>
                  {ficha.completa ? 'Completa' : 'Rascunho'}
                </span>
              )}
            </div>
            <p className="text-sm text-slate-400">
              {ficha ? `${formatDataBr(ficha.data)} — ${ficha.maquina?.nome ?? 'Máquina não definida'}` : 'Carregando…'}
            </p>
          </div>
        </div>

        {ficha && (
          <Link
            to={`/admin/produtos/${produtoId}/semi-acabados/${pecaId}/fichas/${fichaId}/editar`}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 rounded-lg transition-colors"
          >
            <Pencil className="w-4 h-4" />
            Editar
          </Link>
        )}
      </div>

      {loading && (
        <div className="flex items-center justify-center gap-2 py-16 text-slate-400">
          <Loader2 className="w-5 h-5 animate-spin" />
          <span className="text-sm">Carregando…</span>
        </div>
      )}
      {error && (
        <div className="flex items-center justify-center py-16">
          <p className="text-sm text-red-400">{error}</p>
        </div>
      )}

      {!loading && !error && ficha && (
        <>
          {/* Identificação */}
          <section className="bg-[#0f1923] border border-white/5 rounded-xl px-6 py-5">
            <h2 className="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">Identificação</h2>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div><p className={FIELD_LABEL}>Máquina</p><p className={FIELD_VALUE}>{ficha.maquina?.nome ?? '—'}</p></div>
              <div><p className={FIELD_LABEL}>Operador</p><p className={FIELD_VALUE}>{ficha.operario?.user.name ?? '—'}</p></div>
              <div><p className={FIELD_LABEL}>Data</p><p className={FIELD_VALUE}>{formatDataBr(ficha.data)}</p></div>
              <div><p className={FIELD_LABEL}>Quant. Peças/vez</p><p className={FIELD_VALUE}>{ficha.quantidade_pecas_vez ?? '—'}</p></div>
              <div><p className={FIELD_LABEL}>Top Esquerdo (mm)</p><p className={FIELD_VALUE}>{ficha.top_esquerdo_mm ?? '—'}</p></div>
              <div><p className={FIELD_LABEL}>Top Direito (mm)</p><p className={FIELD_VALUE}>{ficha.top_direito_mm ?? '—'}</p></div>
              <div><p className={FIELD_LABEL}>Velocidade de Trabalho</p><p className={FIELD_VALUE}>{ficha.velocidade_trabalho ?? '—'}</p></div>
              {ficha.observacao && (
                <div className="col-span-2 md:col-span-4">
                  <p className={FIELD_LABEL}>Observação</p>
                  <p className={FIELD_VALUE}>{ficha.observacao}</p>
                </div>
              )}
            </div>
          </section>

          {/* Levantamento de Posições de Cabeçotes */}
          <section className="bg-[#0f1923] border border-white/5 rounded-xl px-6 py-5">
            <h2 className="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">Levantamento de Posições de Cabeçotes</h2>
            <div className="bg-white/[0.02] border border-white/5 rounded-lg overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left bg-white/[0.02]">
                    <th className={TH}>Cabeçote</th>
                    <th className={TH}>Sentido</th>
                    <th className={TH}>Largura (mm)</th>
                    <th className={TH}>Deslocamento (mm)</th>
                    <th className={TH}>Altura Cabeçote (mm)</th>
                    <th className={TH}>Obs</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/5">
                  {ficha.posicoes_cabecote.map(p => (
                    <tr key={p.id}>
                      <td className="px-3 py-2 text-white">{p.cabecote}</td>
                      <td className={`${TD} capitalize`}>{p.sentido}</td>
                      <td className={TD}>{p.largura_mm}</td>
                      <td className={TD}>{p.deslocamento_mm}</td>
                      <td className={TD}>{p.altura_cabecote_mm}</td>
                      <td className={TD}>{p.obs ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          {/* Posição das Brocas */}
          <section className="bg-[#0f1923] border border-white/5 rounded-xl px-6 py-5">
            <h2 className="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">Posição das Brocas</h2>
            <div className="bg-white/[0.02] border border-white/5 rounded-lg overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left bg-white/[0.02]">
                    <th className={TH}>Cabeçote</th>
                    <th className={TH}>Sentido</th>
                    <th className={TH}>Posição</th>
                    <th className={TH}>Broca</th>
                    <th className={TH}>Passante / Prof. (mm)</th>
                    <th className={TH}>Agregado</th>
                    <th className={TH}>Obs</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/5">
                  {ficha.posicoes_broca.map(b => (
                    <tr key={b.id}>
                      <td className="px-3 py-2 text-white">{b.cabecote}</td>
                      <td className={`${TD} capitalize`}>{b.sentido}</td>
                      <td className={TD}>{b.posicao}</td>
                      <td className={TD}>{b.broca?.codigo ?? '—'}</td>
                      <td className={TD}>{b.passante ? 'Passante (S)' : `${b.profundidade_mm} mm`}</td>
                      <td className={TD}>{b.agregado ?? '—'}</td>
                      <td className={TD}>{b.obs ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}
    </div>
  )
}
