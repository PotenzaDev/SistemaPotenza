import { X, ClipboardList, FileText, Loader2, Pencil } from 'lucide-react'
import type { ProdutoPecaComProduto } from '@/api/produtos'

interface Props {
  peca: ProdutoPecaComProduto | null
  baixandoPdf: boolean
  onClose: () => void
  onVerPdf: () => void
  onModificar: () => void
}

export function FichaEncontradaModal({ peca, baixandoPdf, onClose, onVerPdf, onModificar }: Props) {
  if (!peca) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={baixandoPdf ? undefined : onClose}
      />

      <div className="relative z-10 w-full max-w-sm bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
        <div className="px-6 py-5 space-y-4">

          <div className="flex items-start gap-3">
            <div className="p-2 rounded-lg bg-[#00aa84]/10 shrink-0">
              <ClipboardList className="w-5 h-5 text-[#00aa84]" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-white leading-tight">Ficha já cadastrada</h2>
              <p className="text-sm text-slate-400 mt-1">
                <span className="font-mono">{peca.numero}</span> — {peca.nome}
              </p>
              <p className="text-xs text-slate-500 mt-0.5">
                Produto: <span className="font-mono">{peca.produto.cod_produto}</span> — {peca.produto.nome}
              </p>
            </div>
          </div>

          <div className="flex gap-3">
            <button
              type="button"
              onClick={onVerPdf}
              disabled={baixandoPdf}
              className="flex-1 py-2 text-sm font-medium text-slate-300 border border-white/10 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              {baixandoPdf ? <Loader2 className="w-4 h-4 animate-spin" /> : <FileText className="w-4 h-4" />}
              Ver PDF
            </button>
            <button
              type="button"
              onClick={onModificar}
              disabled={baixandoPdf}
              className="flex-1 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              <Pencil className="w-4 h-4" />
              Modificar
            </button>
          </div>
        </div>

        {!baixandoPdf && (
          <button
            type="button"
            onClick={onClose}
            className="absolute top-3 right-3 p-1.5 rounded-lg text-white/60 hover:text-white hover:bg-white/10 transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>
    </div>
  )
}
