import { type RefObject, type ReactNode } from 'react'
import { QrCode, ScanLine, Loader2 } from 'lucide-react'

interface BarcodeCardProps {
  titulo: string
  subtitulo: string
  barcode: string
  barcodeOk: boolean
  inputRef: RefObject<HTMLInputElement>
  atualizando: boolean
  botaoLabel: string
  botaoIcone: ReactNode
  onChange: (v: string) => void
  onSubmit: () => void
}

export function BarcodeCard({
  titulo, subtitulo, barcode, barcodeOk, inputRef,
  atualizando, botaoLabel, botaoIcone, onChange, onSubmit,
}: BarcodeCardProps) {
  return (
    <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
      <div className="flex items-center justify-between gap-3 px-6 pt-6 pb-4 border-b border-white/5">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <QrCode className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <p className="text-sm font-semibold text-white">{titulo}</p>
            <p className="text-xs text-slate-500 mt-0.5">{subtitulo}</p>
          </div>
        </div>
        {barcodeOk && (
          <span className="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            <ScanLine className="w-3 h-3" />Lido
          </span>
        )}
      </div>
      <div className="px-6 py-5 space-y-4">
        <input
          ref={inputRef}
          type="text"
          value={barcode}
          onChange={e => onChange(e.target.value)}
          onKeyDown={e => e.key === 'Enter' && barcodeOk && onSubmit()}
          autoComplete="off"
          placeholder="Bipé o código de barras"
          className="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition font-mono tracking-widest"
        />
        <button
          type="button"
          onClick={onSubmit}
          disabled={atualizando || !barcodeOk}
          className="w-full py-2.5 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
        >
          {atualizando
            ? <><Loader2 className="w-4 h-4 animate-spin" />Aguarde…</>
            : <>{botaoIcone}{botaoLabel}</>}
        </button>
      </div>
    </div>
  )
}
