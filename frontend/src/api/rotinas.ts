import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface Rotina {
  id: number
  nome: string
  slug: string
  pagina: string | null
  icone: string
  parent_id: number | null
  ordem: number
  ativo: boolean
  filhos?: Rotina[]
}

export interface CreateRotinaPayload {
  nome: string
  slug: string
  pagina: string | null
  icone: string
  parent_id?: number | null
  ordem?: number
  ativo?: boolean
}

export interface UpdateRotinaPayload {
  nome?: string
  slug?: string
  pagina?: string | null
  icone?: string
  parent_id?: number | null
  ordem?: number
  ativo?: boolean
}

export async function getRotinas(signal?: AbortSignal): Promise<Rotina[]> {
  const res = await apiClient.get<ApiEnvelope<Rotina[]>>('/rotinas', { signal })
  return res.data.data
}

export async function getMenu(signal?: AbortSignal): Promise<Rotina[]> {
  const res = await apiClient.get<ApiEnvelope<Rotina[]>>('/menu', { signal })
  return res.data.data
}

export async function createRotina(payload: CreateRotinaPayload): Promise<Rotina> {
  const res = await apiClient.post<ApiEnvelope<Rotina>>('/rotinas', payload)
  return res.data.data
}

export async function updateRotina(id: number, payload: UpdateRotinaPayload): Promise<Rotina> {
  const res = await apiClient.put<ApiEnvelope<Rotina>>(`/rotinas/${id}`, payload)
  return res.data.data
}

export async function deleteRotina(id: number): Promise<void> {
  await apiClient.delete(`/rotinas/${id}`)
}
