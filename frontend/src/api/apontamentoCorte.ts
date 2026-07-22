import { apiClient } from './client'
import type { ApiEnvelope } from './auth'
import type { Apontamento } from './apontamento'

export interface BiparCortePayload {
  cod_peca: string
  ordem_lote: string
  qtd_peca: number
  pilha: number
  cod_produto: string
  cor_codigo: string
}

export interface ChecklistLoteItem {
  cod_peca: string
  desc_peca: string
  cod_produto: string
  cor_codigo: string
  qtde_total: number
  total_pilhas: number
  qtd_bipada: number
  falta: number
}

export interface FinalizarCortePayload {
  fichas: { ficha_id: number; qtd_produzida: number }[]
  confirmarParcial?: boolean
}

/**
 * Bipa uma ficha no fluxo de corte (por lote). Sem apontamento ativo do
 * lote, cria um novo já em produção (sem setup) e já registra esta bipagem
 * como a primeira ficha; com um já ativo, apenas acrescenta a ficha a ele —
 * de qualquer peça, desde que do mesmo lote.
 */
export async function biparCorte(payload: BiparCortePayload): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>('/apontamento-corte/bipar', payload)
  return res.data.data
}

/** Checklist do lote inteiro: todas as peças esperadas no ERP e o que já foi bipado. */
export async function getChecklistLote(id: number): Promise<ChecklistLoteItem[]> {
  try {
    const res = await apiClient.get<ApiEnvelope<ChecklistLoteItem[]>>(`/apontamento-corte/${id}/checklist`)
    return res.data.data ?? []
  } catch {
    return []
  }
}

/** Finaliza o apontamento de corte com qtd_produzida por ficha. */
export async function finalizarApontamentoCorte(id: number, payload: FinalizarCortePayload): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>(`/apontamento-corte/${id}/finalizar`, {
    fichas: payload.fichas,
    confirmar_parcial: payload.confirmarParcial,
  })
  return res.data.data
}
