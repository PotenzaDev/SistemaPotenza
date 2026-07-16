import { useEffect, useRef, useState } from 'react'
import JsBarcode from 'jsbarcode'
import { X, Printer, Loader2 } from 'lucide-react'
import type { Operario } from '@/api/operarios'
import { baixarCrachaOperarioPdf } from '@/api/operarios'
import { abrirPdfEmNovaAba } from '@/lib/pdf'

interface Props {
  operario: Operario | null
  onClose: () => void
}

export function CrachaOperarioModal({ operario, onClose }: Props) {
  const svgRef = useRef<SVGSVGElement>(null)
  const [gerandoPdf, setGerandoPdf] = useState(false)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    if (!operario || !svgRef.current) return
    JsBarcode(svgRef.current, operario.matricula, {
      format: 'CODE128',
      displayValue: true,
      width: 2,
      height: 60,
      fontSize: 14,
      margin: 8,
    })
  }, [operario])

  useEffect(() => {
    setErro(null)
  }, [operario])

  if (!operario) return null

  async function handleImprimir() {
    if (!operario) return
    setErro(null)
    setGerandoPdf(true)
    try {
      const blob = await baixarCrachaOperarioPdf(operario.id)
      abrirPdfEmNovaAba(blob)
    } catch {
      setErro('Não foi possível gerar o PDF do código de barras.')
    } finally {
      setGerandoPdf(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

      <div className="relative z-10 w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden">

        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h2 className="text-base font-semibold text-slate-900">Crachá do operário</h2>
          <button
            type="button"
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-900 hover:bg-slate-100 transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <div className="px-6 py-8 flex flex-col items-center gap-1 text-center">
          <p className="text-sm font-semibold text-slate-900">{operario.user.name}</p>
          {operario.etapa_fluxo && (
            <p className="text-xs text-slate-500">{operario.etapa_fluxo.nome}</p>
          )}
          <svg ref={svgRef} className="mt-4" />
        </div>

        <div className="px-6 pb-6 space-y-2">
          {erro && <p className="text-xs text-red-600 text-center">{erro}</p>}
          <button
            type="button"
            onClick={handleImprimir}
            disabled={gerandoPdf}
            className="w-full flex items-center justify-center gap-2 py-2.5 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
          >
            {gerandoPdf
              ? <><Loader2 className="w-4 h-4 animate-spin" />Gerando PDF…</>
              : <><Printer className="w-4 h-4" />Gerar PDF do código de barras</>}
          </button>
        </div>
      </div>
    </div>
  )
}
