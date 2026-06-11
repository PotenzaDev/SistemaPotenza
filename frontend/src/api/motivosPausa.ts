import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface MotivoPausa {
  id: number
  nome: string
  ativo: boolean
  is_sistema: boolean
}

/** Motivos que o operario pode selecionar (ativos, nao-sistema). */
export async function getMotivosAtivos(): Promise<MotivoPausa[]> {
  try {
    const res = await apiClient.get<ApiEnvelope<MotivoPausa[]>>('/motivos-pausa/disponiveis')
    return res.data.data ?? []
  } catch {
    return []
  }
}

// ── Admin CRUD ────────────────────────────────────────────────────────────────

export async function getMotivosAdmin(): Promise<MotivoPausa[]> {
  const res = await apiClient.get<ApiEnvelope<MotivoPausa[]>>('/motivos-pausa')
  return res.data.data
}

export async function criarMotivo(nome: string): Promise<MotivoPausa> {
  const res = await apiClient.post<ApiEnvelope<MotivoPausa>>('/motivos-pausa', { nome })
  return res.data.data
}

export async function atualizarMotivo(id: number, data: { nome?: string; ativo?: boolean }): Promise<MotivoPausa> {
  const res = await apiClient.put<ApiEnvelope<MotivoPausa>>(`/motivos-pausa/${id}`, data)
  return res.data.data
}

export async function desativarMotivo(id: number): Promise<void> {
  await apiClient.delete(`/motivos-pausa/${id}`)
}