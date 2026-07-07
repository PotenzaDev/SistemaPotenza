import { useEffect, useState } from 'react'
import axios from 'axios'
import { X, Upload } from 'lucide-react'
import { createBroca, updateBroca, type Broca, type RotacaoBroca } from '@/api/brocas'

interface Props {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  initialData?: Broca   // presente → modo edição
}

interface FormState {
  codigo: string
  espessura_mm: string
  rotacao: RotacaoBroca | ''
  altura_mm: string
  furo_passante: boolean
  ativo: boolean
}

const EMPTY: FormState = {
  codigo: '',
  espessura_mm: '',
  rotacao: '',
  altura_mm: '',
  furo_passante: false,
  ativo: true,
}

function fromBroca(b: Broca): FormState {
  return {
    codigo:        b.codigo,
    espessura_mm:  String(b.espessura_mm),
    rotacao:       b.rotacao,
    altura_mm:     String(b.altura_mm),
    furo_passante: b.furo_passante,
    ativo:         b.ativo,
  }
}

export function BrocaFormModal({ open, onClose, onSuccess, initialData }: Props) {
  const isEdit = !!initialData

  const [form, setForm]     = useState<FormState>(EMPTY)
  const [saving, setSaving] = useState(false)
  const [error, setError]   = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    setForm(initialData ? fromBroca(initialData) : EMPTY)
    setError(null)
  }, [open, initialData])

  function handleField(e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) {
    const { name, value } = e.target
    setForm(prev => ({ ...prev, [name]: value }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)

    if (!form.codigo.trim())     { setError('O campo Código é obrigatório.'); return }
    if (!form.espessura_mm.trim()) { setError('Informe a espessura/diâmetro.'); return }
    if (!form.rotacao)           { setError('Selecione a rotação.'); return }
    if (!form.altura_mm.trim())  { setError('Informe a altura da broca.'); return }

    const payload = {
      codigo:        form.codigo.trim(),
      espessura_mm:  Number(form.espessura_mm),
      rotacao:       form.rotacao,
      altura_mm:     Number(form.altura_mm),
      furo_passante: form.furo_passante,
      ativo:         form.ativo,
    }

    setSaving(true)
    try {
      if (isEdit && initialData) {
        await updateBroca(initialData.id, payload)
      } else {
        await createBroca(payload)
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
        setError('Não foi possível salvar a broca.')
      }
    } finally {
      setSaving(false)
    }
  }

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

      <div className="relative z-10 w-full max-w-lg bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl">

        {/* header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-white/5">
          <h2 className="text-base font-semibold text-white">
            {isEdit ? 'Editar Broca' : 'Cadastrar Broca'}
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

          {/* codigo */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Código <span className="text-red-400">*</span>
            </label>
            <input
              name="codigo"
              value={form.codigo}
              onChange={handleField}
              placeholder="Ex: BR-0001"
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
            />
          </div>

          {/* espessura + altura */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1.5">
                Espessura / Diâmetro (mm) <span className="text-red-400">*</span>
              </label>
              <input
                name="espessura_mm"
                type="number"
                min="0.01"
                step="0.01"
                value={form.espessura_mm}
                onChange={handleField}
                placeholder="Ex: 8.5"
                className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1.5">
                Altura da Broca (mm) <span className="text-red-400">*</span>
              </label>
              <input
                name="altura_mm"
                type="number"
                min="0.01"
                step="0.01"
                value={form.altura_mm}
                onChange={handleField}
                placeholder="Ex: 45"
                className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
              />
            </div>
          </div>

          {/* rotacao */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Rotação <span className="text-red-400">*</span>
            </label>
            <select
              name="rotacao"
              value={form.rotacao}
              onChange={handleField}
              className="w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors"
            >
              <option value="">Selecione a rotação</option>
              <option value="direita">Direita</option>
              <option value="esquerda">Esquerda</option>
            </select>
          </div>

          {/* furo passante + ativo */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div className="flex items-center justify-between py-1">
              <span className="text-xs font-medium text-slate-400">Furo Passante</span>
              <button
                type="button"
                onClick={() => setForm(prev => ({ ...prev, furo_passante: !prev.furo_passante }))}
                className={`relative w-10 h-5 rounded-full transition-colors ${form.furo_passante ? 'bg-[#00aa84]' : 'bg-white/10'}`}
              >
                <span className={`absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform ${form.furo_passante ? 'translate-x-5' : 'translate-x-0'}`} />
              </button>
            </div>
            <div className="flex items-center justify-between py-1">
              <span className="text-xs font-medium text-slate-400">Ativa</span>
              <button
                type="button"
                onClick={() => setForm(prev => ({ ...prev, ativo: !prev.ativo }))}
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
