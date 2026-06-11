import { apiClient } from './client'

export interface User {
  id: number
  name: string
  email: string
  role: 'operario' | 'gestor' | 'admin'
  must_change_password: boolean
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

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout')
}

export async function getMe(): Promise<User> {
  const response = await apiClient.get<ApiEnvelope<User>>('/auth/me')
  return response.data.data
}

export async function changePassword(
  currentPassword: string,
  newPassword: string,
): Promise<void> {
  await apiClient.post('/auth/change-password', {
    current_password:      currentPassword,
    password:              newPassword,
    password_confirmation: newPassword,
  })
}
