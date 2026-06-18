import { apiClient } from './client'

export interface ActivityLog {
  id: number
  user_id: number | null
  user_name: string
  action: string
  description: string
  ip_address: string | null
  created_at: string
}

export interface ActivityLogFilters {
  from?: string
  to?: string
  action?: string
  per_page?: number
  page?: number
}

interface PaginatedLogs {
  data: ActivityLog[]
  current_page: number
  last_page: number
  total: number
  per_page: number
}

interface ApiEnvelope<T> {
  success: boolean
  data: T
}

export async function getActivityLogs(filters: ActivityLogFilters = {}): Promise<PaginatedLogs> {
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== undefined && v !== '')
  )
  const response = await apiClient.get<ApiEnvelope<PaginatedLogs>>('/admin/activity-logs', { params })
  return response.data.data
}
