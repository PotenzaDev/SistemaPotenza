import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface SessaoMaquinaRegra {
  possui_setup: boolean
  possui_producao: boolean
  permite_multiplas_passagens: boolean
  limite_passagens: number | null
}

export interface SessaoMaquina {
  id: number
  nome: string
  etapa_fluxo: { id: number; nome: string; ordem: number } | null
  regra_maquina?: SessaoMaquinaRegra
}

export interface PausaOciosa {
  id: number
  motivo: string | null
  inicio: string
}

export interface Sessao {
  id: number
  inicio: string
  fim: string | null
  ativa: boolean
  maquina: SessaoMaquina
  pausa_ociosa: PausaOciosa | null
}

export async function iniciarSessao(
  maquinaId: number,
  sessaoPausadaId?: number,
  turnoInformadoInicio?: string,
  turnoInformadoFim?: string,
): Promise<Sessao> {
  const res = await apiClient.post<ApiEnvelope<Sessao>>('/sessao/iniciar', {
    maquina_id: maquinaId,
    sessao_pausada_id: sessaoPausadaId,
    turno_informado_inicio: turnoInformadoInicio,
    turno_informado_fim: turnoInformadoFim,
  })
  return res.data.data
}

export interface SessaoPausada {
  id: number
  cod_peca: string | null
  ordem_lote: string | null
  desc_peca: string | null
  pausada_em: string | null
}

export async function getSessoesPausadas(maquinaId: number): Promise<SessaoPausada[]> {
  const res = await apiClient.get<ApiEnvelope<SessaoPausada[]>>('/sessao/pausadas', {
    params: { maquina_id: maquinaId },
  })
  return res.data.data
}

export async function encerrarSessao(): Promise<void> {
  await apiClient.post('/sessao/encerrar')
}

export async function cancelarSessao(): Promise<void> {
  await apiClient.post('/sessao/cancelar')
}

export async function pausarSessao(): Promise<Sessao> {
  const res = await apiClient.post<ApiEnvelope<Sessao>>('/sessao/pausar')
  return res.data.data
}

export async function pausarSessaoOciosa(motivoId: number): Promise<Sessao> {
  const res = await apiClient.post<ApiEnvelope<Sessao>>('/sessao/pausar-ociosa', { motivo_pausa_id: motivoId })
  return res.data.data
}

export async function retomarSessaoOciosa(): Promise<Sessao> {
  const res = await apiClient.post<ApiEnvelope<Sessao>>('/sessao/retomar-ociosa')
  return res.data.data
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
