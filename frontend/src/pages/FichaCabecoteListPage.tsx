import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import axios from 'axios'
import { ArrowLeft, ClipboardList, Loader2, Plus } from 'lucide-react'
import { listFichasCabecote, type FichaCabecoteResumo } from '@/api/fichasCabecote'
import { getProduto, type Produto } from '@/api/produtos'

function formatDataBr(iso: string | null): string {
  if (!iso) return '—'
  const [y, m, d] = iso.slice(0, 10).split('-')
  return `${d}/${m}/${y}`
}

export function FichaCabecoteListPage() {
  const navigate = useNavigate()
  const { produtoId, pecaId } = useParams<{ produtoId: string; pecaId: string }>()

  const [produto, setProduto] = useState<Produto | null>(null)
  const [fichas, setFichas] = useState<FichaCabecoteResumo[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback((signal?: AbortSignal) => {
    if (!produtoId || !pecaId) return
    setLoading(true)
    setError(null)
    Promise.all([
      getProduto(Number(produtoId), signal),
      listFichasCabecote(Number(pecaId), signal),
    ])
      .then(([p, f]) => {
        setProduto(p)
        setFichas(f)
      })
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar as fichas.')
      })
      .finally(() => {
        if (!signal?.aborted) setLoading(false)
      })
  }, [produtoId, pecaId])

  useEffect(() => {
    const controller = new AbortController()
    load(controller.signal)
    return () => controller.abort()
  }, [load])

  const peca = produto?.pecas?.find(p => p.id === Number(pecaId))

  return (
    <div className="space-y-6">

      {/* cabeçalho */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => navigate(`/admin/produtos/${produtoId}/semi-acabados`)}
            className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
            title="Voltar para Semi-Acabados"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <ClipboardList className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Fichas de Cabeçote</h1>
            <p className="text-sm text-slate-400">
              {peca ? <>Nº {peca.numero} — {peca.nome}</> : 'Carregando semi-acabado…'}
            </p>
          </div>
        </div>

        <Link
          to={`/admin/produtos/${produtoId}/semi-acabados/${pecaId}/fichas/nova`}
          className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 rounded-lg transition-colors"
        >
          <Plus className="w-4 h-4" />
          Nova Ficha
        </Link>
      </div>

      {/* tabela */}
      <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
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
        {!loading && !error && fichas.length === 0 && (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-slate-500">Nenhuma ficha preenchida para este semi-acabado.</p>
          </div>
        )}
        {!loading && !error && fichas.length > 0 && (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-white/5 text-left">
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Data</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Máquina</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Operador</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {fichas.map(ficha => (
                <tr
                  key={ficha.id}
                  onClick={() => navigate(`/admin/produtos/${produtoId}/semi-acabados/${pecaId}/fichas/${ficha.id}`)}
                  className="hover:bg-white/[0.02] transition-colors cursor-pointer"
                >
                  <td className="px-4 py-3 text-slate-300">{formatDataBr(ficha.data)}</td>
                  <td className="px-4 py-3 text-white">{ficha.maquina?.nome ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-300">{ficha.operario?.user.name ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-0.5 rounded-full ${ficha.completa ? 'bg-[#00aa84]/10 text-[#00aa84]' : 'bg-amber-400/10 text-amber-400'}`}>
                      {ficha.completa ? 'Completa' : 'Rascunho'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
