import { apiClient } from './client'
import type { ApiEnvelope, User } from './auth'

export type UsuarioSistema = User

export interface CreateUsuarioSistemaPayload {
  name: string
  email: string
  password: string
  role: 'admin' | 'funcionario'
  rotina_ids?: number[]
}

export interface UpdateUsuarioSistemaPayload {
  name?: string
  email?: string
  password?: string
  role?: 'admin' | 'funcionario'
  rotina_ids?: number[]
  ativo?: boolean
}

export async function getUsuariosSistema(signal?: AbortSignal): Promise<UsuarioSistema[]> {
  const res = await apiClient.get<ApiEnvelope<UsuarioSistema[]>>('/usuarios', { signal })
  return res.data.data
}

export async function createUsuarioSistema(payload: CreateUsuarioSistemaPayload): Promise<UsuarioSistema> {
  const res = await apiClient.post<ApiEnvelope<UsuarioSistema>>('/usuarios', payload)
  return res.data.data
}

export async function updateUsuarioSistema(id: number, payload: UpdateUsuarioSistemaPayload): Promise<UsuarioSistema> {
  const res = await apiClient.put<ApiEnvelope<UsuarioSistema>>(`/usuarios/${id}`, payload)
  return res.data.data
}

export async function deleteUsuarioSistema(id: number): Promise<void> {
  await apiClient.delete(`/usuarios/${id}`)
}
