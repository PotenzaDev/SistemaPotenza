import { useEffect, useState } from 'react'
import axios from 'axios'
import { X, Eye, EyeOff } from 'lucide-react'
import { getEtapasFluxo, type EtapaFluxo } from '@/api/etapasFluxo'
import { createOperario, updateOperario, type Operario } from '@/api/operarios'

interface Props {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  initialData?: Operario
}

interface FormState {
  name: string
  email: string
  password: string
  etapa_fluxo_id: string
  ativo: boolean
}

const EMPTY: FormState = {
  name: '',
  email: '',
  password: '',
  etapa_fluxo_id: '',
  ativo: true,
}

function fromOperario(o: Operario): FormState {
  return {
    name:           o.user.name,
    email:          o.user.email,
    password:       '',
    etapa_fluxo_id: o.etapa_fluxo_id ? String(o.etapa_fluxo_id) : '',
    ativo:          o.user.ativo,
  }
}

export function OperarioFormModal({ open, onClose, onSuccess, initialData }: Props) {
  const isEdit = !!initialData

  const [form, setForm]         = useState<FormState>(EMPTY)
  const [showPass, setShowPass] = useState(false)
  const [etapas, setEtapas]     = useState<EtapaFluxo[]>([])
  const [saving, setSaving]     = useState(false)
  const [error, setError]       = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    const controller = new AbortController()

    setForm(initialData ? fromOperario(initialData) : EMPTY)
    setShowPass(false)
    setError(null)

    getEtapasFluxo(controller.signal)
      .then(setEtapas)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar os setores.')
      })

    return () => controller.abort()
  }, [open, initialData])

  function handleField(e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) {
    const { name, value } = e.target
    setForm(prev => ({ ...prev, [name]: value }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)

    if (!form.name.trim())    { setError('O nome é obrigatório.'); return }
    if (!form.email.trim())   { setError('O e-mail é obrigatório.'); return }
    if (!form.etapa_fluxo_id) { setError('Selecione o setor do operário.'); return }

    if (!isEdit) {
      if (!form.password)           { setError('A senha é obrigatória.'); return }
      if (form.password.length < 6) { setError('A senha deve ter pelo menos 6 caracteres.'); return }
    } else if (form.password && form.password.length < 6) {
      setError('A nova senha deve ter pelo menos 6 caracteres.')
      return
    }

    setSaving(true)
    try {
      if (isEdit && initialData) {
        await updateOperario(initialData.id, {
          name:           form.name.trim(),
          email:          form.email.trim(),
          password:       form.password || undefined,
          etapa_fluxo_id: Number(form.etapa_fluxo_id),
          ativo:          form.ativo,
        })
      } else {
        await createOperario({
          name:           form.name.trim(),
          email:          form.email.trim(),
          password:       form.password,
          etapa_fluxo_id: Number(form.etapa_fluxo_id),
        })
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
        setError(isEdit ? 'Não foi possível atualizar o operário.' : 'Não foi possível cadastrar o operário.')
      }
    } finally {
      setSaving(false)
    }
  }

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

      <div className="relative z-10 w-full max-w-md bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl">

        {/* header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-white/5">
          <h2 className="text-base font-semibold text-white">
            {isEdit ? 'Editar Operário' : 'Cadastrar Operário'}
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
              name="name"
              value={form.name}
              onChange={handleField}
              placeholder="Ex: João Silva"
              autoComplete="off"
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
            />
          </div>

          {/* e-mail */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              E-mail <span className="text-red-400">*</span>
            </label>
            <input
              name="email"
              type="email"
              value={form.email}
              onChange={handleField}
              placeholder="joao@empresa.com"
              autoComplete="off"
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
            />
          </div>

          {/* senha */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              {isEdit ? 'Nova senha' : 'Senha'}{' '}
              {!isEdit && <span className="text-red-400">*</span>}
              {isEdit && <span className="text-slate-600">(deixe vazio para não alterar)</span>}
            </label>
            <div className="relative">
              <input
                name="password"
                type={showPass ? 'text' : 'password'}
                value={form.password}
                onChange={handleField}
                placeholder="Mínimo 6 caracteres"
                autoComplete="new-password"
                className="w-full px-3 py-2 pr-10 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
              />
              <button
                type="button"
                onClick={() => setShowPass(p => !p)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
              >
                {showPass ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

          {/* setor */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Setor <span className="text-red-400">*</span>
            </label>
            <select
              name="etapa_fluxo_id"
              value={form.etapa_fluxo_id}
              onChange={handleField}
              className="w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors"
            >
              <option value="">Selecione o setor</option>
              {etapas.map(e => (
                <option key={e.id} value={String(e.id)}>{e.nome}</option>
              ))}
            </select>
          </div>

          {/* ativo — só em modo edição */}
          {isEdit && (
            <div className="flex items-center justify-between py-1">
              <div>
                <span className="text-xs font-medium text-slate-400">Conta ativa</span>
                <p className="text-xs text-slate-600">
                  {form.ativo ? 'Operário pode fazer login' : 'Acesso bloqueado'}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setForm(prev => ({ ...prev, ativo: !prev.ativo }))}
                className={`relative w-10 h-5 rounded-full transition-colors ${form.ativo ? 'bg-[#00aa84]' : 'bg-white/10'}`}
              >
                <span className={`absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform ${form.ativo ? 'translate-x-5' : 'translate-x-0'}`} />
              </button>
            </div>
          )}

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
              className="flex-1 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
            >
              {saving ? 'Salvando…' : isEdit ? 'Salvar alterações' : 'Cadastrar'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
