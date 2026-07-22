import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface EtapaFluxo {
  id: number
  nome: string
  ordem: number
  requer_config_cabecote: boolean
  apontamento_por_lote: boolean
}

export async function getEtapasFluxo(signal?: AbortSignal): Promise<EtapaFluxo[]> {
  const res = await apiClient.get<ApiEnvelope<EtapaFluxo[]>>('/etapas-fluxo', { signal })
  return res.data.data
}
