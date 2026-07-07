import { useEffect, useState } from 'react'
import axios from 'axios'
import { X, Upload } from 'lucide-react'
import { createRotina, updateRotina, type Rotina } from '@/api/rotinas'
import { ICON_OPTIONS, getIcon } from '@/lib/iconRegistry'
import { PAGE_REGISTRY } from '@/lib/pageRegistry'

interface Props {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  initialData?: Rotina   // presente → modo edição
  paisDisponiveis: Rotina[]
}

interface FormState {
  nome: string
  slug: string
  pagina: string
  icone: string
  parent_id: string
  ordem: string
  ativo: boolean
}

const EMPTY: FormState = {
  nome: '',
  slug: '',
  pagina: '',
  icone: '',
  parent_id: '',
  ordem: '',
  ativo: true,
}

function fromRotina(r: Rotina): FormState {
  return {
    nome:      r.nome,
    slug:      r.slug,
    pagina:    r.pagina ?? '',
    icone:     r.icone,
    parent_id: r.parent_id ? String(r.parent_id) : '',
    ordem:     String(r.ordem),
    ativo:     r.ativo,
  }
}

export function RotinaFormModal({ open, onClose, onSuccess, initialData, paisDisponiveis }: Props) {
  const isEdit = !!initialData

  const [form, setForm]     = useState<FormState>(EMPTY)
  const [saving, setSaving] = useState(false)
  const [error, setError]   = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    setForm(initialData ? fromRotina(initialData) : EMPTY)
    setError(null)
  }, [open, initialData])

  const opcoesPai = paisDisponiveis.filter((r) => r.id !== initialData?.id)

  function handleField(e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)

    if (!form.nome.trim())   { setError('O nome é obrigatório.'); return }
    if (!form.slug.trim())   { setError('O slug é obrigatório.'); return }
    if (form.parent_id && !form.pagina.trim()) { setError('Selecione uma página.'); return }
    if (!form.icone.trim())  { setError('Selecione um ícone.'); return }
    if (!/^[a-z0-9_]+$/.test(form.slug.trim())) {
      setError('O slug deve conter apenas letras minúsculas, números e underline.')
      return
    }

    const payload = {
      nome:      form.nome.trim(),
      slug:      form.slug.trim(),
      pagina:    form.pagina.trim() || null,
      icone:     form.icone.trim(),
      parent_id: form.parent_id ? Number(form.parent_id) : null,
      ordem:     form.ordem.trim() ? Number(form.ordem) : undefined,
      ativo:     form.ativo,
    }

    setSaving(true)
    try {
      if (isEdit && initialData) {
        await updateRotina(initialData.id, payload)
      } else {
        await createRotina(payload)
      }
      onSuccess()
      onClose()
    } catch (err: unknown) {
      if (axios.isAxiosError(err) && err.response?.data?.errors) {
        const msgs = Object.values(err.response.data.errors as Record<string, string[]>)
          .flat()
          .join(' ')
        setError(msgs)
      } else {
        setError('Não foi possível salvar a rotina.')
      }
    } finally {
      setSaving(false)
    }
  }

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

      <div className="relative z-10 w-full max-w-lg bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">

        {/* header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-white/5">
          <h2 className="text-base font-semibold text-white">
            {isEdit ? 'Editar Rotina' : 'Cadastrar Rotina'}
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-4">

          {/* nome */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Nome <span className="text-red-400">*</span>
            </label>
            <input
              name="nome"
              value={form.nome}
              onChange={handleField}
              placeholder="Ex: Cadastro"
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
            />
          </div>

          {/* slug */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Slug <span className="text-red-400">*</span>
            </label>
            <input
              name="slug"
              value={form.slug}
              onChange={handleField}
              placeholder="Ex: cadastro"
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
            />
            <p className="text-xs text-slate-600 mt-1">Apenas letras minúsculas, números e underline.</p>
          </div>

          {/* pagina */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Página {form.parent_id && <span className="text-red-400">*</span>}
            </label>
            <select
              name="pagina"
              value={form.pagina}
              onChange={handleField}
              className="w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors"
            >
              <option value="">Nenhuma (grupo apenas)</option>
              {PAGE_REGISTRY.map((p) => (
                <option key={p.value} value={p.value}>{p.label}</option>
              ))}
            </select>
            <p className="text-xs text-slate-600 mt-1">
              Deixe em branco para criar uma rotina pai (grupo) — ela só expande os submenus, sem abrir página.
            </p>
          </div>

          {/* icone */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Ícone <span className="text-red-400">*</span>
            </label>
            <div className="grid grid-cols-8 gap-1.5 p-2 bg-white/[0.02] border border-white/10 rounded-lg">
              {ICON_OPTIONS.map(({ value, Icon }) => (
                <button
                  key={value}
                  type="button"
                  title={value}
                  onClick={() => setForm((prev) => ({ ...prev, icone: value }))}
                  className={`flex items-center justify-center p-2 rounded-lg transition-colors ${
                    form.icone === value
                      ? 'bg-[#00aa84]/20 text-[#00aa84] ring-1 ring-[#00aa84]/50'
                      : 'text-slate-400 hover:bg-white/5 hover:text-white'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                </button>
              ))}
            </div>
            {form.icone && (() => {
              const Icon = getIcon(form.icone)
              return (
                <p className="text-xs text-slate-500 mt-1.5 flex items-center gap-1.5">
                  <Icon className="w-3.5 h-3.5" /> Selecionado: {form.icone}
                </p>
              )
            })()}
          </div>

          {/* rotina pai */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">Rotina Pai</label>
            <select
              name="parent_id"
              value={form.parent_id}
              onChange={handleField}
              className="w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors"
            >
              <option value="">Nenhuma (rotina de topo)</option>
              {opcoesPai.map((r) => (
                <option key={r.id} value={String(r.id)}>{r.nome}</option>
              ))}
            </select>
          </div>

          {/* ordem + ativo */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1.5">Ordem</label>
              <input
                name="ordem"
                type="number"
                min="0"
                value={form.ordem}
                onChange={handleField}
                placeholder="0"
                className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
              />
            </div>
            <div className="flex items-center justify-between py-1">
              <span className="text-xs font-medium text-slate-400">Ativa</span>
              <button
                type="button"
                onClick={() => setForm((prev) => ({ ...prev, ativo: !prev.ativo }))}
                className={`relative w-10 h-5 rounded-full transition-colors ${form.ativo ? 'bg-[#00aa84]' : 'bg-white/10'}`}
              >
                <span className={`absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform ${form.ativo ? 'translate-x-5' : 'translate-x-0'}`} />
              </button>
            </div>
          </div>

          {/* erro */}
          {error && (
            <p className="text-xs text-red-400 bg-red-400/10 border border-red-400/20 rounded-lg px-3 py-2">
              {error}
            </p>
          )}

          {/* botões */}
          <div className="flex gap-3 pt-1">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-2 text-sm font-medium text-slate-400 bg-white/5 hover:bg-white/10 rounded-lg transition-colors"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={saving}
              className="flex-1 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              {saving
                ? <><Upload className="w-3.5 h-3.5 animate-bounce" />Salvando…</>
                : isEdit ? 'Salvar alterações' : 'Salvar'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
