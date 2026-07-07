import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { Upload, Search, ArrowLeft, Loader2, CheckCircle } from 'lucide-react'
import {
  buscarProdutosErp,
  buscarSubGruposErp,
  importarProduto,
  type ErpProduto,
  type EmpresaErp,
} from '@/api/produtos'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

const erpProdutoColumns: ResponsiveTableColumn<ErpProduto>[] = [
  {
    key: 'cod_produto',
    header: 'Código',
    cellClassName: 'px-4 py-3 font-medium text-white font-mono text-xs',
    render: (p) => p.cod_produto,
  },
  { key: 'nome', header: 'Nome', render: (p) => p.nome },
  { key: 'grupo', header: 'Grupo', render: (p) => p.grupo },
  { key: 'sub_grupo', header: 'Sub-Grupo', render: (p) => p.sub_grupo },
  {
    key: 'ja_importado',
    header: 'Status',
    render: (p) =>
      p.ja_importado
        ? <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-500/10 text-emerald-400">Já importado</span>
        : <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-500/10 text-slate-400">Novo</span>,
  },
]

interface FiltrosState {
  empresa: EmpresaErp | ''
  subGrupo: string
  nome: string
}

const EMPTY_FILTROS: FiltrosState = {
  empresa: '',
  subGrupo: '',
  nome: '',
}

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

export function ImportarProdutoPage() {
  const navigate = useNavigate()

  const [filtros, setFiltros]     = useState<FiltrosState>(EMPTY_FILTROS)
  const [subGrupos, setSubGrupos] = useState<string[]>([])
  const [resultado, setResultado] = useState<ErpProduto[] | null>(null)

  const [buscando, setBuscando] = useState(false)
  const [error, setError]       = useState<string | null>(null)
  const [importingCod, setImportingCod] = useState<string | null>(null)
  const [sucesso, setSucesso]   = useState<string | null>(null)

  useEffect(() => {
    if (!filtros.empresa) {
      setSubGrupos([])
      return
    }

    const controller = new AbortController()
    buscarSubGruposErp({ empresa: filtros.empresa }, controller.signal)
      .then(setSubGrupos)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setSubGrupos([])
      })

    return () => controller.abort()
  }, [filtros.empresa])

  const canBuscar = !!filtros.empresa && (filtros.nome.trim() !== '' || filtros.subGrupo !== '')

  async function handleBuscar() {
    if (!filtros.empresa || !canBuscar) return
    setError(null)
    setSucesso(null)
    setBuscando(true)
    try {
      const produtos = await buscarProdutosErp({
        empresa: filtros.empresa,
        nome: filtros.nome.trim() || undefined,
        sub_grupo: filtros.subGrupo || undefined,
      })
      setResultado(produtos)
    } catch (err: unknown) {
      setError(parseError(err, 'Não foi possível buscar produtos no ERP.'))
      setResultado(null)
    } finally {
      setBuscando(false)
    }
  }

  async function handleImportar(produto: ErpProduto) {
    if (!filtros.empresa) return
    setError(null)
    setSucesso(null)
    setImportingCod(produto.cod_produto)
    try {
      await importarProduto({
        cod_produto: produto.cod_produto,
        nome:        produto.nome,
        grupo:       produto.grupo,
        sub_grupo:   produto.sub_grupo,
        empresa:     filtros.empresa,
      })
      setSucesso(`Produto "${produto.nome}" importado com sucesso.`)
      setResultado(prev =>
        prev?.map(p =>
          p.cod_produto === produto.cod_produto ? { ...p, ja_importado: true } : p
        ) ?? prev
      )
    } catch (err: unknown) {
      setError(parseError(err, 'Não foi possível importar o produto.'))
    } finally {
      setImportingCod(null)
    }
  }

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
          <Upload className="w-5 h-5 text-[#00aa84]" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-white">Importar Produto</h1>
          <p className="text-sm text-slate-400">Busque produtos no ERP e importe junto com seus semi-acabados</p>
        </div>
      </div>

      {/* sucesso */}
      {sucesso && (
        <div className="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-4 py-3">
          <CheckCircle className="w-5 h-5 text-emerald-400 mt-0.5 shrink-0" />
          <p className="text-sm text-emerald-400 font-medium">{sucesso}</p>
        </div>
      )}

      {/* filtros */}
      <div className="bg-[#0f1923] border border-white/5 rounded-xl px-6 py-5 space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">

          {/* empresa */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Empresa <span className="text-red-400">*</span>
            </label>
            <select
              value={filtros.empresa}
              onChange={(e) =>
                setFiltros(prev => ({ ...prev, empresa: e.target.value as EmpresaErp | '', subGrupo: '' }))
              }
              className="w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors"
            >
              <option value="">Selecione a empresa</option>
              <option value="FBM">FBM</option>
              <option value="FBP">FBP</option>
            </select>
          </div>

          {/* nome */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Nome do Produto
            </label>
            <input
              value={filtros.nome}
              onChange={(e) => setFiltros(prev => ({ ...prev, nome: e.target.value }))}
              placeholder="Ex: Mesa de Escritório"
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
            />
          </div>

          {/* sub-grupo */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Sub-Grupo
            </label>
            <select
              value={filtros.subGrupo}
              onChange={(e) => setFiltros(prev => ({ ...prev, subGrupo: e.target.value }))}
              disabled={!filtros.empresa}
              className="w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <option value="">Selecione o sub-grupo</option>
              {subGrupos.map(sg => (
                <option key={sg} value={sg}>{sg}</option>
              ))}
            </select>
          </div>
        </div>

        {/* erro */}
        {error && (
          <p className="text-xs text-red-400 bg-red-400/10 border border-red-400/20 rounded-lg px-3 py-2">
            {error}
          </p>
        )}

        {!canBuscar && (
          <p className="text-xs text-slate-500">
            Preencha o nome ou selecione um sub-grupo.
          </p>
        )}

        <div className="flex justify-end pt-1">
          <button
            type="button"
            onClick={handleBuscar}
            disabled={!canBuscar || buscando}
            className="px-6 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            {buscando
              ? <><Loader2 className="w-3.5 h-3.5 animate-spin" />Buscando…</>
              : <><Search className="w-3.5 h-3.5" />Buscar</>}
          </button>
        </div>
      </div>

      {/* resultado */}
      {resultado !== null && (
        <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
          {resultado.length === 0 ? (
            <div className="flex items-center justify-center py-16">
              <p className="text-sm text-slate-500">Nenhum produto encontrado no ERP.</p>
            </div>
          ) : (
            <ResponsiveTable
              columns={erpProdutoColumns}
              data={resultado}
              keyExtractor={(produto) => produto.cod_produto}
              renderActions={(produto) => (
                <button
                  type="button"
                  onClick={() => handleImportar(produto)}
                  disabled={importingCod === produto.cod_produto}
                  className="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                >
                  {importingCod === produto.cod_produto
                    ? <Loader2 className="w-3.5 h-3.5 animate-spin" />
                    : <><Upload className="w-3.5 h-3.5" />Importar</>}
                </button>
              )}
            />
          )}
        </div>
      )}
    </div>
  )
}
