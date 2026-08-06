import { apiClient } from './client'
import type { ApiEnvelope } from './auth'
import type { Apontamento } from './apontamento'

export interface BiparColadeiraPayload {
  cod_peca: string
  ordem_lote: string
  cod_produto: string
  cor_codigo: string
}

export interface BiparFichaColadeiraPayload {
  cod_peca: string
  ordem_lote: string
  qtd_peca: number
  pilha: number
  cod_produto: string
  cor_codigo: string
  confirmar?: boolean
}

/** Passo 1: bipar lote → cria apontamento, inicia setup e grava as dimensões da ficha técnica (comprimento/largura/etc). */
export async function biparColadeira(payload: BiparColadeiraPayload): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>('/apontamento-coladeira/bipar', payload)
  return res.data.data
}

/** Passo 3: bipar ficha de produção — grava a ficha já com metros_lineares_borda calculado. */
export async function biparFichaColadeira(id: number, payload: BiparFichaColadeiraPayload): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>(`/apontamento-coladeira/${id}/bipar-ficha`, payload)
  return res.data.data
}
