import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import axios from 'axios'
import { ArrowLeft, ClipboardList, FileText, Layers, Loader2, Printer } from 'lucide-react'
import { getProduto, type Produto, type ProdutoPeca } from '@/api/produtos'
import {
  baixarFichaCabecoteBrancoPdf,
  baixarFichaCabecotePdf,
  baixarFichasCabecoteBrancoPdfLote,
  baixarFichasCabecotePdfLote,
} from '@/api/fichasCabecote'
import { abrirPdfEmNovaAba } from '@/lib/pdf'

export function ProdutoSemiAcabadosPage() {
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()

  const [produto, setProduto] = useState<Produto | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState<string | null>(null)
  const [printError, setPrintError] = useState<string | null>(null)
  const [impressoesEmAndamento, setImpressoesEmAndamento] = useState<Set<string>>(new Set())
  const [selecionados, setSelecionados] = useState<Set<number>>(new Set())
  const [loteEmAndamento, setLoteEmAndamento] = useState<'branco' | 'preenchida' | null>(null)

  const load = useCallback((signal?: AbortSignal) => {
    if (!id) return
    setLoading(true)
    setError(null)
    getProduto(Number(id), signal)
      .then(setProduto)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar o produto.')
      })
      .finally(() => {
        if (!signal?.aborted) setLoading(false)
      })
  }, [id])

  useEffect(() => {
    const controller = new AbortController()
    load(controller.signal)
    return () => controller.abort()
  }, [load])

  const pecas = produto?.pecas ?? []

  const executarImpressao = useCallback(async (chave: string, baixar: () => Promise<Blob>) => {
    setPrintError(null)
    setImpressoesEmAndamento(prev => new Set(prev).add(chave))
    try {
      const blob = await baixar()
      abrirPdfEmNovaAba(blob)
    } catch {
      setPrintError('Não foi possível gerar o PDF da ficha.')
    } finally {
      setImpressoesEmAndamento(prev => {
        const proximo = new Set(prev)
        proximo.delete(chave)
        return proximo
      })
    }
  }, [])

  const imprimirFichaPreenchida = useCallback((peca: ProdutoPeca) => {
    if (!peca.ultima_ficha_cabecote) return
    const fichaId = peca.ultima_ficha_cabecote.id
    void executarImpressao(`preenchida-${peca.id}`, () => baixarFichaCabecotePdf(fichaId))
  }, [executarImpressao])

  const imprimirFichaBranco = useCallback((peca: ProdutoPeca) => {
    void executarImpressao(`branco-${peca.id}`, () => baixarFichaCabecoteBrancoPdf(peca.id))
  }, [executarImpressao])

  const toggleSelecionado = useCallback((pecaId: number) => {
    setSelecionados(prev => {
      const proximo = new Set(prev)
      if (proximo.has(pecaId)) {
        proximo.delete(pecaId)
      } else {
        proximo.add(pecaId)
      }
      return proximo
    })
  }, [])

  const todosSelecionados = pecas.length > 0 && pecas.every(peca => selecionados.has(peca.id))

  const toggleSelecionarTodos = useCallback(() => {
    setSelecionados(prev => (prev.size === pecas.length ? new Set() : new Set(pecas.map(peca => peca.id))))
  }, [pecas])

  const pecasSelecionadasComFicha = useMemo(
    () => pecas.filter(peca => selecionados.has(peca.id) && peca.ultima_ficha_cabecote),
    [pecas, selecionados],
  )

  const imprimirLoteBranco = useCallback(async () => {
    setPrintError(null)
    setLoteEmAndamento('branco')
    try {
      const blob = await baixarFichasCabecoteBrancoPdfLote(Array.from(selecionados))
      abrirPdfEmNovaAba(blob)
    } catch {
      setPrintError('Não foi possível gerar o PDF em lote das fichas em branco.')
    } finally {
      setLoteEmAndamento(null)
    }
  }, [selecionados])

  const imprimirLotePreenchida = useCallback(async () => {
    setPrintError(null)
    setLoteEmAndamento('preenchida')
    try {
      const fichaIds = pecasSelecionadasComFicha.map(peca => peca.ultima_ficha_cabecote!.id)
      const blob = await baixarFichasCabecotePdfLote(fichaIds)
      abrirPdfEmNovaAba(blob)
    } catch {
      setPrintError('Não foi possível gerar o PDF em lote das fichas preenchidas.')
    } finally {
      setLoteEmAndamento(null)
    }
  }, [pecasSelecionadasComFicha])

  return (
    <div className="space-y-6">

      {/* cabeçalho */}
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={() => navigate('/admin/produtos')}
          className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
          title="Voltar para Produtos"
        >
          <ArrowLeft className="w-5 h-5" />
        </button>
        <div className="p-2 rounded-lg bg-[#00aa84]/10">
          <Layers className="w-5 h-5 text-[#00aa84]" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-white">Semi-Acabados</h1>
          <p className="text-sm text-slate-400">
            {produto
              ? <><span className="font-mono">{produto.cod_produto}</span> — {produto.nome}</>
              : 'Carregando produto…'}
          </p>
        </div>
      </div>

      {printError && (
        <div className="px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/20 text-sm text-red-400">
          {printError}
        </div>
      )}

      {/* impressão em lote */}
      {pecas.length > 0 && (
        <div className="flex items-center gap-3">
          <span className="text-sm text-slate-400">
            {selecionados.size > 0 ? `${selecionados.size} selecionada(s)` : 'Selecione as peças para imprimir em lote'}
          </span>
          <button
            type="button"
            onClick={() => void imprimirLoteBranco()}
            disabled={selecionados.size === 0 || loteEmAndamento !== null}
            className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-white/10 hover:bg-white/20 rounded-lg transition-colors disabled:opacity-30"
          >
            {loteEmAndamento === 'branco' ? <Loader2 className="w-4 h-4 animate-spin" /> : <FileText className="w-4 h-4" />}
            Imprimir em branco selecionadas
          </button>
          <button
            type="button"
            onClick={() => void imprimirLotePreenchida()}
            disabled={pecasSelecionadasComFicha.length === 0 || loteEmAndamento !== null}
            title={pecasSelecionadasComFicha.length === 0 ? 'Nenhuma peça selecionada tem ficha preenchida' : undefined}
            className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-white/10 hover:bg-white/20 rounded-lg transition-colors disabled:opacity-30"
          >
            {loteEmAndamento === 'preenchida' ? <Loader2 className="w-4 h-4 animate-spin" /> : <Printer className="w-4 h-4" />}
            Imprimir preenchidas selecionadas
          </button>
        </div>
      )}

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
        {!loading && !error && pecas.length === 0 && (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-slate-500">Nenhum semi-acabado encontrado para este produto.</p>
          </div>
        )}
        {!loading && !error && pecas.length > 0 && (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-white/5 text-left">
                <th className="px-4 py-3 w-10">
                  <input
                    type="checkbox"
                    checked={todosSelecionados}
                    onChange={toggleSelecionarTodos}
                    aria-label="Selecionar todas as peças"
                    className="w-4 h-4 rounded border-white/20"
                  />
                </th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Nº</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Nome</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Sub-Grupo</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Material</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Dimensão</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Ficha</th>
                <th className="px-4 py-3 w-10"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {pecas.map(peca => (
                <tr key={peca.id} className="hover:bg-white/[0.02] transition-colors">
                  <td className="px-4 py-3">
                    <input
                      type="checkbox"
                      checked={selecionados.has(peca.id)}
                      onChange={() => toggleSelecionado(peca.id)}
                      aria-label={`Selecionar peça ${peca.numero}`}
                      className="w-4 h-4 rounded border-white/20"
                    />
                  </td>
                  <td className="px-4 py-3 font-mono text-xs text-slate-300">{peca.numero}</td>
                  <td className="px-4 py-3 text-white">{peca.nome}</td>
                  <td className="px-4 py-3 text-slate-300">{peca.sub_grupo ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-300">{peca.material ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-300">{peca.dimensao ?? '—'}</td>
                  <td className="px-4 py-3">
                    {(peca.fichas_cabecote_count ?? 0) > 0 ? (
                      <span className="text-xs px-2 py-0.5 rounded-full bg-[#00aa84]/10 text-[#00aa84]">Preenchida</span>
                    ) : (
                      <span className="text-xs px-2 py-0.5 rounded-full bg-white/5 text-slate-500">Pendente</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-1">
                      <Link
                        to={`/admin/produtos/${id}/semi-acabados/${peca.id}/fichas`}
                        title="Fichas de Cabeçote"
                        className="inline-flex p-1.5 rounded-lg text-slate-400 hover:text-[#00aa84] hover:bg-white/10 transition-colors"
                      >
                        <ClipboardList className="w-4 h-4" />
                      </Link>
                      <button
                        type="button"
                        onClick={() => imprimirFichaPreenchida(peca)}
                        disabled={!peca.ultima_ficha_cabecote || impressoesEmAndamento.has(`preenchida-${peca.id}`)}
                        title={peca.ultima_ficha_cabecote ? 'Imprimir ficha preenchida' : 'Nenhuma ficha cadastrada'}
                        className="inline-flex p-1.5 rounded-lg text-slate-400 hover:text-[#00aa84] hover:bg-white/10 transition-colors disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-slate-400"
                      >
                        {impressoesEmAndamento.has(`preenchida-${peca.id}`)
                          ? <Loader2 className="w-4 h-4 animate-spin" />
                          : <Printer className="w-4 h-4" />}
                      </button>
                      <button
                        type="button"
                        onClick={() => imprimirFichaBranco(peca)}
                        disabled={impressoesEmAndamento.has(`branco-${peca.id}`)}
                        title="Imprimir ficha em branco"
                        className="inline-flex p-1.5 rounded-lg text-slate-400 hover:text-[#00aa84] hover:bg-white/10 transition-colors disabled:opacity-30"
                      >
                        {impressoesEmAndamento.has(`branco-${peca.id}`)
                          ? <Loader2 className="w-4 h-4 animate-spin" />
                          : <FileText className="w-4 h-4" />}
                      </button>
                    </div>
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
