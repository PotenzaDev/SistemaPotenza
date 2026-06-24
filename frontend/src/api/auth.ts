import { apiClient } from './client'

export interface UserRotina {
  id: number
  slug: string
  nome: string
  parent_id: number | null
}

export interface User {
  id: number
  name: string
  email: string
  role: 'operario' | 'gestor' | 'admin' | 'funcionario'
  must_change_password: boolean
  ativo?: boolean
  rotinas?: UserRotina[]
}

export interface LoginPayload {
  email: string
  password: string
}

export interface LoginResponse {
  user: User
  token: string
  requires_password_change: boolean
}

export interface ApiEnvelope<T> {
  success: boolean
  data: T
  message: string
}

export async function login(payload: LoginPayload): Promise<LoginResponse> {
  const response = await apiClient.post<ApiEnvelope<LoginResponse>>(
    '/auth/login',
    payload,
  )
  return response.data.data
}

export async function loginCracha(matricula: string): Promise<LoginResponse> {
  const response = await apiClient.post<ApiEnvelope<LoginResponse>>(
    '/auth/login-cracha',
    { matricula },
  )
  return response.data.data
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout')
}

export async function getMe(): Promise<User> {
  const response = await apiClient.get<ApiEnvelope<User>>('/auth/me')
  return response.data.data
}

export interface UpdateProfilePayload {
  name: string
  current_password?: string
  new_password?: string
}

export async function updateProfile(payload: UpdateProfilePayload): Promise<User> {
  const response = await apiClient.put<ApiEnvelope<User>>('/auth/profile', payload)
  return response.data.data
}

export async function changePassword(
  currentPassword: string,
  newPassword: string,
): Promise<void> {
  const body: Record<string, string> = {
    password:              newPassword,
    password_confirmation: newPassword,
  }
  if (currentPassword) {
    body.current_password = currentPassword
  }
  await apiClient.post('/auth/change-password', body)
}
