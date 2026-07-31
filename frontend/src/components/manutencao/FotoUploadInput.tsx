import { useEffect, useMemo, useRef, useState } from 'react'
import { Camera, ImagePlus, X } from 'lucide-react'

export interface FotoUploadInputProps {
  fotos: File[]
  onChange: (fotos: File[]) => void
  maxFotos?: number
  maxSizeMb?: number
}

const DEFAULT_MAX_FOTOS = 6
const DEFAULT_MAX_SIZE_MB = 5

export function FotoUploadInput({
  fotos,
  onChange,
  maxFotos = DEFAULT_MAX_FOTOS,
  maxSizeMb = DEFAULT_MAX_SIZE_MB,
}: FotoUploadInputProps) {
  const [erro, setErro] = useState<string | null>(null)
  const cameraInputRef = useRef<HTMLInputElement>(null)
  const galeriaInputRef = useRef<HTMLInputElement>(null)

  const previews = useMemo(() => fotos.map(f => URL.createObjectURL(f)), [fotos])

  useEffect(() => {
    return () => { previews.forEach(url => URL.revokeObjectURL(url)) }
  }, [previews])

  function adicionarArquivos(lista: FileList | null) {
    if (!lista || lista.length === 0) return
    setErro(null)

    const novos = Array.from(lista)
    const maxBytes = maxSizeMb * 1024 * 1024
    const grandeDemais = novos.some(f => f.size > maxBytes)
    if (grandeDemais) {
      setErro(`Cada foto deve ter no máximo ${maxSizeMb}MB.`)
      return
    }

    const combinadas = [...fotos, ...novos]
    if (combinadas.length > maxFotos) {
      setErro(`Máximo de ${maxFotos} fotos.`)
      onChange(combinadas.slice(0, maxFotos))
      return
    }

    onChange(combinadas)
  }

  function removerFoto(index: number) {
    setErro(null)
    onChange(fotos.filter((_, i) => i !== index))
  }

  const atingiuLimite = fotos.length >= maxFotos

  return (
    <div className="space-y-2">
      <div className="flex gap-2">
        <button
          type="button"
          onClick={() => cameraInputRef.current?.click()}
          disabled={atingiuLimite}
          className="flex-1 h-12 flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 text-sm font-medium text-slate-300 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <Camera className="w-4 h-4" />
          Tirar foto
        </button>
        <button
          type="button"
          onClick={() => galeriaInputRef.current?.click()}
          disabled={atingiuLimite}
          className="flex-1 h-12 flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 text-sm font-medium text-slate-300 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <ImagePlus className="w-4 h-4" />
          Galeria
        </button>
      </div>

      <input
        ref={cameraInputRef}
        type="file"
        accept="image/*"
        capture="environment"
        className="hidden"
        onChange={e => { adicionarArquivos(e.target.files); e.target.value = '' }}
      />
      <input
        ref={galeriaInputRef}
        type="file"
        accept="image/*"
        multiple
        className="hidden"
        onChange={e => { adicionarArquivos(e.target.files); e.target.value = '' }}
      />

      {erro && <p className="text-xs text-red-400">{erro}</p>}

      {previews.length > 0 && (
        <div className="grid grid-cols-3 gap-2">
          {previews.map((url, i) => (
            <div key={url} className="relative aspect-square rounded-lg overflow-hidden border border-white/10">
              <img src={url} alt={`Foto ${i + 1}`} className="w-full h-full object-cover" />
              <button
                type="button"
                onClick={() => removerFoto(i)}
                className="absolute top-1 right-1 p-1 rounded-full bg-black/70 text-white hover:bg-black/90 transition-colors"
              >
                <X className="w-3 h-3" />
              </button>
            </div>
          ))}
        </div>
      )}

      <p className="text-xs text-slate-500">{fotos.length}/{maxFotos} fotos</p>
    </div>
  )
}
