export const BARCODE_LENGTH = 18

export interface ParsedBarcode {
  cod_peca: string
  ordem_lote: string
  qtd_peca: number
  pilha: number
}

/* Formato do código de barras (18 chars):
   [0..6]   = cod_peca   (7 chars)
   [7..11]  = ordem_lote (5 chars)
   [12..15] = qtd_peca   (4 chars)
   [16..17] = pilha      (2 chars) */
export function parseBarcode(raw: string): ParsedBarcode | null {
  if (raw.length !== BARCODE_LENGTH) return null
  const qtd   = parseInt(raw.slice(12, 16), 10)
  const pilha = parseInt(raw.slice(16, 18), 10)
  if (isNaN(qtd) || isNaN(pilha) || qtd < 1 || pilha < 1) return null
  return {
    cod_peca:   raw.slice(0, 7),
    ordem_lote: raw.slice(7, 12),
    qtd_peca:   qtd,
    pilha,
  }
}
