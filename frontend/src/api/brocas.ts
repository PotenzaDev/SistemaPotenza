import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export type RotacaoBroca = 'direita' | 'esquerda'

export interface Broca {
  id: number
  codigo: string
  espessura_mm: number
  rotacao: RotacaoBroca
  altura_mm: number
  furo_passante: boolean
  ativo: boolean
}

export interface CreateBrocaPayload {
  codigo: string
  espessura_mm: number
  rotacao: RotacaoBroca
  altura_mm: number
  furo_passante: boolean
  ativo?: boolean
}

export interface UpdateBrocaPayload {
  codigo?: string
  espessura_mm?: number
  rotacao?: RotacaoBroca
  altura_mm?: number
  furo_passante?: boolean
  ativo?: boolean
}

export async function getBrocas(signal?: AbortSignal): Promise<Broca[]> {
  const res = await apiClient.get<ApiEnvelope<Broca[]>>('/brocas', { signal })
  return res.data.data
}

export async function createBroca(payload: CreateBrocaPayload): Promise<Broca> {
  const res = await apiClient.post<ApiEnvelope<Broca>>('/brocas', payload)
  return res.data.data
}

export async function updateBroca(id: number, payload: UpdateBrocaPayload): Promise<Broca> {
  const res = await apiClient.put<ApiEnvelope<Broca>>(`/brocas/${id}`, payload)
  return res.data.data
}

export async function deleteBroca(id: number): Promise<void> {
  await apiClient.delete(`/brocas/${id}`)
}
