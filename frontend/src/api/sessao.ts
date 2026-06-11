import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface SessaoMaquina {
  id: number
  nome: string
  etapa_fluxo: { id: number; nome: string; ordem: number } | null
}

export interface Sessao {
  id: number
  inicio: string
  fim: string | null
  ativa: boolean
  maquina: SessaoMaquina
}

export async function iniciarSessao(maquinaId: number): Promise<Sessao> {
  const res = await apiClient.post<ApiEnvelope<Sessao>>('/sessao/iniciar', {
    maquina_id: maquinaId,
  })
  return res.data.data
}

export async function encerrarSessao(): Promise<void> {
  await apiClient.post('/sessao/encerrar')
}

export async function encerrarTurno(): Promise<void> {
  await apiClient.post('/sessao/encerrar-turno')
}

export async function getSessaoAtiva(): Promise<Sessao | null> {
  try {
    const res = await apiClient.get<ApiEnvelope<Sessao>>('/sessao/ativa')
    return res.data.data
  } catch {
    return null
  }
}

export interface TurnoHoje {
  hora_inicio: string
  hora_fim: string
  tolerancia_finalizacao_minutos: number
}

export async function getTurnoHoje(): Promise<TurnoHoje | null> {
  try {
    const res = await apiClient.get<ApiEnvelope<TurnoHoje>>('/sessao/turno-hoje')
    return res.data.data
  } catch {
    return null
  }
}
