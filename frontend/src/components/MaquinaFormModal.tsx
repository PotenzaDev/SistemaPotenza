import { useEffect, useRef, useState } from 'react'
import axios from 'axios'
import { X, ImageIcon, Upload } from 'lucide-react'
import { getEtapasFluxo, type EtapaFluxo } from '@/api/etapasFluxo'
import { createMaquina, updateMaquina, type Maquina } from '@/api/maquinas'

interface Props {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  initialData?: Maquina   // presente → modo edição
}

interface FormState {
  nome: string
  codigo: string
  ano: string
  descricao: string
  etapa_fluxo_id: string
  ativa: boolean
}

const EMPTY: FormState = {
  nome: '',
  codigo: '',
  ano: '',
  descricao: '',
  etapa_fluxo_id: '',
  ativa: true,
}

function fromMaquina(m: Maquina): FormState {
  return {
    nome:          m.nome,
    codigo:        m.codigo ?? '',
    ano:           m.ano ? String(m.ano) : '',
    descricao:     m.descricao ?? '',
    etapa_fluxo_id: String(m.etapa_fluxo_id),
    ativa:         m.ativa,
  }
}

export function MaquinaFormModal({ open, onClose, onSuccess, initialData }: Props) {
  const isEdit = !!initialData

  const [form, setForm]             = useState<FormState>(EMPTY)
  const [foto, setFoto]             = useState<File | null>(null)
  const [blobPreview, setBlobPreview] = useState<string | null>(null)
  const [etapas, setEtapas]         = useState<EtapaFluxo[]>([])
  const [saving, setSaving]         = useState(false)
  const [error, setError]           = useState<string | null>(null)
  const fileRef                     = useRef<HTMLInputElement>(null)

  /* inicializa ao abrir */
  useEffect(() => {
    if (!open) return
    const controller = new AbortController()

    setForm(initialData ? fromMaquina(initialData) : EMPTY)
    setFoto(null)
    setBlobPreview(null)
    setError(null)

    getEtapasFluxo(controller.signal)
      .then(setEtapas)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar os grupos.')
      })

    return () => controller.abort()
  }, [open, initialData])

  /* gera/revoga blob URL quando o usuário seleciona um novo arquivo */
  useEffect(() => {
    if (!foto) { setBlobPreview(null); return }
    const url = URL.createObjectURL(foto)
    setBlobPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [foto])

  /* preview a exibir: novo arquivo > foto já salva */
  const displayPreview = blobPreview ?? (isEdit ? initialData?.foto_url ?? null : null)

  function handleField(e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) {
    const { name, value } = e.target
    setForm(prev => ({ ...prev, [name]: value }))
  }

  function handleFile(e: React.ChangeEvent<HTMLInputElement>) {
    setFoto(e.target.files?.[0] ?? null)
  }

  function clearFoto() {
    setFoto(null)
    if (fileRef.current) fileRef.current.value = ''
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)

    if (!form.nome.trim())    { setError('O campo Modelo é obrigatório.'); return }
    if (!form.etapa_fluxo_id) { setError('Selecione um grupo.'); return }
    if (foto && !foto.type.startsWith('image/')) {
      setError('O arquivo selecionado não é uma imagem. Use PNG, JPG ou WEBP.')
      return
    }

    const data = new FormData()
    data.append('nome',            form.nome.trim())
    data.append('etapa_fluxo_id',  form.etapa_fluxo_id)
    data.append('ativa',           form.ativa ? '1' : '0')
    if (form.codigo.trim())    data.append('codigo',    form.codigo.trim())
    if (form.ano.trim())       data.append('ano',       form.ano.trim())
    if (form.descricao.trim()) data.append('descricao', form.descricao.trim())
    if (foto)                  data.append('foto',      foto)

    setSaving(true)
    try {
      if (isEdit && initialData) {
        await updateMaquina(initialData.id, data)
      } else {
        await createMaquina(data)
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
        setError('Não foi possível salvar a máquina.')
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
            {isEdit ? 'Editar Máquina' : 'Cadastrar Máquina'}
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

          {/* foto */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">Foto</label>
            <button
              type="button"
              onClick={() => fileRef.current?.click()}
              className="w-full h-36 rounded-xl border border-dashed border-white/15 hover:border-[#00aa84]/50 bg-white/[0.02] hover:bg-[#00aa84]/5 transition-all flex flex-col items-center justify-center gap-2 overflow-hidden"
            >
              {displayPreview ? (
                <img src={displayPreview} alt="preview" className="w-full h-full object-cover" />
              ) : (
                <>
                  <div className="p-2.5 rounded-full bg-white/5">
                    <ImageIcon className="w-5 h-5 text-slate-500" />
                  </div>
                  <span className="text-xs text-slate-500">Clique para selecionar uma imagem</span>
                  <span className="text-xs text-slate-600">PNG, JPG, WEBP — máx. 2 MB</span>
                </>
              )}
            </button>
            <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={handleFile} />
            {foto && (
              <div className="flex items-center justify-between mt-1.5 px-1">
                <span className="text-xs text-slate-400 truncate">{foto.name}</span>
                <button type="button" onClick={clearFoto} className="text-xs text-red-400 hover:text-red-300 ml-2 shrink-0">
                  Remover
                </button>
              </div>
            )}
          </div>

          {/* modelo */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Modelo <span className="text-red-400">*</span>
            </label>
            <input
              name="nome"
              value={form.nome}
              onChange={handleField}
              placeholder="Ex: CNC Romi"
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
            />
          </div>

          {/* codigo + ano */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1.5">Código</label>
              <input
                name="codigo"
                value={form.codigo}
                onChange={handleField}
                placeholder="Ex: MAQ-001"
                className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1.5">Ano</label>
              <input
                name="ano"
                type="number"
                value={form.ano}
                onChange={handleField}
                placeholder={String(new Date().getFullYear())}
                min="1900"
                max="2100"
                className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors"
              />
            </div>
          </div>

          {/* grupo */}
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1.5">
              Grupo <span className="text-red-400">*</span>
            </label>
            <select
              name="etapa_fluxo_id"
              value={form.etapa_fluxo_id}
              onChange={handleField}
              className="w-full px-3 py-2 text-sm bg-[#0f1923] border border-white/10 rounded-lg text-white focus:outline-none focus:border-[#00aa84]/60 transition-colors"
            >
              <option value="">Selecione um grupo</option>
              {etapas.map(e => (
                <option key={e.id} value={String(e.id)}>{e.nome}</option>
              ))}
            </select>
          </div>

          {/* ativa */}
          <div className="flex items-center justify-between py-1">
            <span className="text-xs font-medium text-slate-400">Ativa</span>
            <button
              type="button"
              onClick={() => setForm(prev => ({ ...prev, ativa: !prev.ativa }))}
              className={`relative w-10 h-5 rounded-full transition-colors ${form.ativa ? 'bg-[#00aa84]' : 'bg-white/10'}`}
            >
              <span className={`absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform ${form.ativa ? 'translate-x-5' : 'translate-x-0'}`} />
            </button>
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
