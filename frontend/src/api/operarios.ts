import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface OperarioUser {
  id: number
  name: string
  email: string
  ativo: boolean
}

export interface OperarioEtapa {
  id: number
  nome: string
}

export interface Operario {
  id: number
  matricula: string
  cargo: string | null
  etapa_fluxo_id: number | null
  user: OperarioUser
  etapa_fluxo: OperarioEtapa | null
}

export interface CreateOperarioPayload {
  name: string
  email: string
  password: string
  etapa_fluxo_id: number
}

export interface UpdateOperarioPayload {
  name?: string
  email?: string
  password?: string
  etapa_fluxo_id?: number
  ativo?: boolean
}

export async function getOperarios(signal?: AbortSignal): Promise<Operario[]> {
  const res = await apiClient.get<ApiEnvelope<Operario[]>>('/operarios', { signal })
  return res.data.data
}

export async function createOperario(payload: CreateOperarioPayload): Promise<Operario> {
  const res = await apiClient.post<ApiEnvelope<Operario>>('/operarios', payload)
  return res.data.data
}

export async function updateOperario(id: number, payload: UpdateOperarioPayload): Promise<Operario> {
  const res = await apiClient.put<ApiEnvelope<Operario>>(`/operarios/${id}`, payload)
  return res.data.data
}

export async function baixarCrachaOperarioPdf(id: number): Promise<Blob> {
  const res = await apiClient.get(`/operarios/${id}/cracha-pdf`, { responseType: 'blob' })
  return res.data
}
