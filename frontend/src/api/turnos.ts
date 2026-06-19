import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface TurnoDia {
  dia_semana: number
  hora_inicio: string | null
  hora_fim: string | null
  intervalo_inicio: string | null
  intervalo_fim: string | null
  tolerancia_finalizacao_minutos: number
  ativo: boolean
}

export interface AtualizarTurnoData {
  hora_inicio: string
  hora_fim: string
  intervalo_inicio: string | null
  intervalo_fim: string | null
  tolerancia_finalizacao_minutos: number
  ativo: boolean
}

export async function getTurnos(): Promise<TurnoDia[]> {
  const res = await apiClient.get<ApiEnvelope<TurnoDia[]>>('/turnos')
  return res.data.data
}

export async function atualizarTurno(diaSemana: number, data: AtualizarTurnoData): Promise<TurnoDia> {
  const res = await apiClient.put<ApiEnvelope<TurnoDia>>(`/turnos/${diaSemana}`, data)
  return res.data.data
}
