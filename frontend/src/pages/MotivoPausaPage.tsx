import { useEffect, useState } from 'react'
import { Plus, Pencil, Power, Loader2, AlertCircle, Shield, X, Check } from 'lucide-react'
import {
  getMotivosAdmin,
  criarMotivo,
  atualizarMotivo,
  desativarMotivo,
  type MotivoPausa,
} from '@/api/motivosPausa'

export function MotivoPausaPage() {
  const [motivos, setMotivos]       = useState<MotivoPausa[]>([])
  const [loading, setLoading]       = useState(true)
  const [erroApi, setErroApi]       = useState<string | null>(null)

  // Formulário de criação
  const [showForm, setShowForm]     = useState(false)
  const [novoNome, setNovoNome]     = useState('')
  const [salvando, setSalvando]     = useState(false)

  // Edição inline
  const [editandoId, setEditandoId] = useState<number | null>(null)
  const [editNome, setEditNome]     = useState('')
  const [salvandoId, setSalvandoId] = useState<number | null>(null)

  useEffect(() => { carregarMotivos() }, [])

  async function carregarMotivos() {
    setLoading(true)
    try {
      setMotivos(await getMotivosAdmin())
    } catch {
      setErroApi('Erro ao carregar motivos.')
    } finally {
      setLoading(false)
    }
  }

  async function handleCriar(e: React.FormEvent) {
    e.preventDefault()
    const nome = novoNome.trim()
    if (!nome) return
    setSalvando(true); setErroApi(null)
    try {
      const criado = await criarMotivo(nome)
      setMotivos(prev => [...prev, criado])
      setNovoNome('')
      setShowForm(false)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setSalvando(false)
    }
  }

  function iniciarEdicao(m: MotivoPausa) {
    setEditandoId(m.id)
    setEditNome(m.nome)
    setErroApi(null)
  }

  function cancelarEdicao() {
    setEditandoId(null)
    setEditNome('')
  }

  async function handleSalvarEdicao(m: MotivoPausa) {
    const nome = editNome.trim()
    if (!nome || nome === m.nome) { cancelarEdicao(); return }
    setSalvandoId(m.id); setErroApi(null)
    try {
      const atualizado = await atualizarMotivo(m.id, { nome })
      setMotivos(prev => prev.map(x => x.id === m.id ? atualizado : x))
      cancelarEdicao()
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setSalvandoId(null)
    }
  }

  async function handleToggleAtivo(m: MotivoPausa) {
    if (m.is_sistema) return
    setSalvandoId(m.id); setErroApi(null)
    try {
      if (m.ativo) {
        await desativarMotivo(m.id)
        setMotivos(prev => prev.map(x => x.id === m.id ? { ...x, ativo: false } : x))
      } else {
        const atualizado = await atualizarMotivo(m.id, { ativo: true })
        setMotivos(prev => prev.map(x => x.id === m.id ? atualizado : x))
      }
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setSalvandoId(null)
    }
  }

  return (
    <div className="space-y-6">
      {/* Cabeçalho */}
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold text-white">Motivos de Pausa</h1>
          <p className="text-sm text-slate-500 mt-0.5">
            Gerencie os motivos disponíveis para os operadores pausarem um apontamento.
          </p>
        </div>
        <button
          type="button"
          onClick={() => { setShowForm(true); setErroApi(null) }}
          className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] transition-colors shrink-0"
        >
          <Plus className="w-4 h-4" />
          Novo motivo
        </button>
      </div>

      {/* Erro global */}
      {erroApi && (
        <div className="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
          <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
          <p className="text-xs text-red-400">{erroApi}</p>
        </div>
      )}

      {/* Formulário de criação */}
      {showForm && (
        <div className="bg-[#0f1923] border border-white/5 rounded-xl px-5 py-4">
          <p className="text-sm font-semibold text-white mb-3">Novo motivo de pausa</p>
          <form onSubmit={handleCriar} className="flex gap-2">
            <input
              type="text"
              value={novoNome}
              onChange={e => setNovoNome(e.target.value)}
              placeholder="Ex: Manutenção, Troca de ferramenta…"
              autoFocus
              maxLength={100}
              className="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition"
            />
            <button
              type="submit"
              disabled={salvando || !novoNome.trim()}
              className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-1.5 shrink-0"
            >
              {salvando ? <Loader2 className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4" />}
              Salvar
            </button>
            <button
              type="button"
              onClick={() => { setShowForm(false); setNovoNome('') }}
              className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors"
            >
              <X className="w-4 h-4" />
            </button>
          </form>
        </div>
      )}

      {/* Lista */}
      <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-16 gap-2 text-slate-400">
            <Loader2 className="w-4 h-4 animate-spin" />
            <span className="text-sm">Carregando…</span>
          </div>
        ) : motivos.length === 0 ? (
          <div className="text-center py-16">
            <p className="text-sm text-slate-500">Nenhum motivo cadastrado.</p>
            <p className="text-xs text-slate-600 mt-1">Clique em "Novo motivo" para adicionar.</p>
          </div>
        ) : (
          <div className="divide-y divide-white/5">
            {/* Cabeçalho da tabela */}
            <div className="hidden sm:grid grid-cols-[1fr_100px_80px] gap-4 px-5 py-3 border-b border-white/5">
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nome</p>
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Status</p>
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Ações</p>
            </div>

            {motivos.map(m => (
              <div key={m.id} className="grid grid-cols-[1fr_auto] sm:grid-cols-[1fr_100px_80px] gap-4 items-center px-5 py-3.5">
                {/* Nome */}
                <div className="min-w-0">
                  {editandoId === m.id ? (
                    <div className="flex gap-2">
                      <input
                        type="text"
                        value={editNome}
                        onChange={e => setEditNome(e.target.value)}
                        onKeyDown={e => {
                          if (e.key === 'Enter') void handleSalvarEdicao(m)
                          if (e.key === 'Escape') cancelarEdicao()
                        }}
                        autoFocus
                        maxLength={100}
                        className="flex-1 min-w-0 px-2 py-1 bg-white/5 border border-[#00aa84]/40 rounded-lg text-sm text-white focus:outline-none focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <button
                        type="button"
                        onClick={() => void handleSalvarEdicao(m)}
                        disabled={salvandoId === m.id}
                        className="shrink-0 p-1.5 rounded-lg text-[#00aa84] hover:bg-[#00aa84]/10 disabled:opacity-50 transition-colors"
                      >
                        {salvandoId === m.id ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Check className="w-3.5 h-3.5" />}
                      </button>
                      <button
                        type="button"
                        onClick={cancelarEdicao}
                        className="shrink-0 p-1.5 rounded-lg text-slate-400 hover:bg-white/5 transition-colors"
                      >
                        <X className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="text-sm font-medium text-white truncate">{m.nome}</span>
                      {m.is_sistema && (
                        <span
                          title="Criado pelo sistema — não pode ser editado"
                          className="shrink-0 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider bg-blue-500/15 text-blue-400 border border-blue-500/20"
                        >
                          <Shield className="w-2.5 h-2.5" />
                          Sistema
                        </span>
                      )}
                    </div>
                  )}
                </div>

                {/* Status */}
                <div className="flex justify-center">
                  {m.ativo ? (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400">
                      Ativo
                    </span>
                  ) : (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700/50 text-slate-500">
                      Inativo
                    </span>
                  )}
                </div>

                {/* Ações */}
                <div className="flex items-center gap-1 justify-end">
                  {!m.is_sistema && editandoId !== m.id && (
                    <button
                      type="button"
                      title="Editar nome"
                      onClick={() => iniciarEdicao(m)}
                      className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors"
                    >
                      <Pencil className="w-3.5 h-3.5" />
                    </button>
                  )}
                  {!m.is_sistema && (
                    <button
                      type="button"
                      title={m.ativo ? 'Desativar' : 'Reativar'}
                      onClick={() => void handleToggleAtivo(m)}
                      disabled={salvandoId === m.id}
                      className={[
                        'p-1.5 rounded-lg transition-colors disabled:opacity-50',
                        m.ativo
                          ? 'text-slate-400 hover:text-red-400 hover:bg-red-500/10'
                          : 'text-slate-400 hover:text-emerald-400 hover:bg-emerald-500/10',
                      ].join(' ')}
                    >
                      {salvandoId === m.id
                        ? <Loader2 className="w-3.5 h-3.5 animate-spin" />
                        : <Power className="w-3.5 h-3.5" />}
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <p className="text-xs text-slate-600">
        Motivos <strong className="text-blue-400">Sistema</strong> são criados automaticamente e não podem ser editados.
        Desativar um motivo o oculta dos operadores, mas preserva o histórico existente.
      </p>
    </div>
  )
}

function apiMsg(err: unknown): string {
  return (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    ?? 'Erro inesperado. Tente novamente.'
}
