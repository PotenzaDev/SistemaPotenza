import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import axios from 'axios'
import { Package, CheckCircle2, XCircle, Loader2, Plus, Trash2, Layers } from 'lucide-react'
import { getProdutos, deleteProduto, type Produto } from '@/api/produtos'
import { useAuth } from '@/hooks/useAuth'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

type Filtro = 'todos' | 'ativos' | 'inativos'

const FILTROS: { value: Filtro; label: string }[] = [
  { value: 'todos',    label: 'Todos'    },
  { value: 'ativos',   label: 'Ativos'   },
  { value: 'inativos', label: 'Inativos' },
]

const produtoColumns: ResponsiveTableColumn<Produto>[] = [
  {
    key: 'cod_produto',
    header: 'Código',
    render: (p) => p.cod_produto,
    cellClassName: 'px-4 py-3 font-medium text-white font-mono text-xs',
  },
  { key: 'nome', header: 'Nome', render: (p) => p.nome },
  { key: 'grupo', header: 'Grupo', render: (p) => p.grupo },
  { key: 'sub_grupo', header: 'Sub-Grupo', render: (p) => p.sub_grupo },
  { key: 'empresa', header: 'Empresa', render: (p) => p.empresa },
  { key: 'semis', header: 'Semis', render: (p) => p.pecas_count ?? '—' },
  {
    key: 'status',
    header: 'Status',
    render: (p) =>
      p.ativo ? (
        <span className="inline-flex items-center gap-1.5 text-[#00aa84]">
          <CheckCircle2 className="w-4 h-4" /> Ativo
        </span>
      ) : (
        <span className="inline-flex items-center gap-1.5 text-slate-500">
          <XCircle className="w-4 h-4" /> Inativo
        </span>
      ),
  },
]

export function ProdutosPage() {
  const { user }                     = useAuth()
  const navigate                     = useNavigate()
  const [produtos, setProdutos]     = useState<Produto[]>([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState<string | null>(null)
  const [filtro, setFiltro]         = useState<Filtro>('ativos')
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const canCreate = user?.role === 'admin' || user?.role === 'funcionario'

  const load = useCallback((signal?: AbortSignal) => {
    setLoading(true)
    setError(null)
    getProdutos(signal)
      .then(setProdutos)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar os produtos.')
      })
      .finally(() => {
        if (!signal?.aborted) setLoading(false)
      })
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    load(controller.signal)
    return () => controller.abort()
  }, [load])

  const filtered = useMemo(() => {
    if (filtro === 'ativos')   return produtos.filter(p => p.ativo)
    if (filtro === 'inativos') return produtos.filter(p => !p.ativo)
    return produtos
  }, [produtos, filtro])

  async function handleDesativar(produto: Produto) {
    if (!window.confirm(`Deseja desativar o produto "${produto.nome}"?`)) return
    setDeletingId(produto.id)
    try {
      await deleteProduto(produto.id)
      load()
    } catch {
      window.alert('Não foi possível desativar o produto.')
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="space-y-6">

      {/* cabeçalho */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <Package className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Produtos</h1>
            <p className="text-sm text-slate-400">Gerencie o cadastro de produtos</p>
          </div>
        </div>

        {canCreate && (
          <Link
            to="/admin/produtos/importar"
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 rounded-lg transition-colors"
          >
            <Plus className="w-4 h-4" />
            Importar Produto
          </Link>
        )}
      </div>

      {/* filtros */}
      <div className="flex items-center gap-1 p-1 bg-white/5 rounded-lg w-fit">
        {FILTROS.map(f => (
          <button
            key={f.value}
            onClick={() => setFiltro(f.value)}
            className={`px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
              filtro === f.value
                ? 'bg-[#00aa84] text-white'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            {f.label}
          </button>
        ))}
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
        {!loading && !error && filtered.length === 0 && (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-slate-500">
              {filtro === 'todos'
                ? 'Nenhum produto cadastrado.'
                : filtro === 'ativos'
                  ? 'Nenhum produto ativo.'
                  : 'Nenhum produto inativo.'}
            </p>
          </div>
        )}
        {!loading && !error && filtered.length > 0 && (
          <ResponsiveTable
            columns={produtoColumns}
            data={filtered}
            keyExtractor={(p) => p.id}
            renderActions={(p) => (
              <>
                <button
                  onClick={() => navigate(`/admin/produtos/${p.id}/semi-acabados`)}
                  title="Ver semi-acabados"
                  className="p-1.5 rounded-lg text-slate-400 hover:text-[#00aa84] hover:bg-white/10 transition-colors"
                >
                  <Layers className="w-4 h-4" />
                </button>
                {canCreate && p.ativo && (
                  <button
                    onClick={() => handleDesativar(p)}
                    disabled={deletingId === p.id}
                    title="Desativar"
                    className="p-1.5 rounded-lg text-slate-400 hover:text-red-400 hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {deletingId === p.id
                      ? <Loader2 className="w-4 h-4 animate-spin" />
                      : <Trash2 className="w-4 h-4" />}
                  </button>
                )}
              </>
            )}
          />
        )}
      </div>
    </div>
  )
}
