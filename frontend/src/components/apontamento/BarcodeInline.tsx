import { type RefObject } from 'react'
import { Loader2, ScanLine } from 'lucide-react'

interface BarcodeInlineProps {
  barcode: string
  barcodeOk: boolean
  inputRef: RefObject<HTMLInputElement>
  atualizando: boolean
  botaoLabel: string
  onChange: (v: string) => void
  onSubmit: () => void
}

export function BarcodeInline({
  barcode, barcodeOk, inputRef, atualizando, botaoLabel, onChange, onSubmit,
}: BarcodeInlineProps) {
  return (
    <div className="flex gap-2">
      <input
        ref={inputRef}
        type="text"
        value={barcode}
        onChange={e => onChange(e.target.value)}
        onKeyDown={e => e.key === 'Enter' && barcodeOk && onSubmit()}
        autoComplete="off"
        placeholder="Bipé o código de barras"
        className="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/50 focus:ring-1 focus:ring-[#00aa84]/30 transition font-mono"
      />
      <button
        type="button"
        onClick={onSubmit}
        disabled={atualizando || !barcodeOk}
        className="px-4 py-2 text-sm font-semibold text-white bg-[#00aa84] hover:bg-[#009973] disabled:opacity-40 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center gap-1.5 shrink-0"
      >
        {atualizando ? <Loader2 className="w-4 h-4 animate-spin" /> : <ScanLine className="w-4 h-4" />}
        {botaoLabel}
      </button>
    </div>
  )
}
