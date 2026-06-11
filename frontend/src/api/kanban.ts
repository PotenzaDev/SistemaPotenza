import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface LoteKanban {
  id: number
  ordem_lote: string
  cod_peca: string
  total_pilhas: number
  pilhas_concluidas: number
  percentual: number
  status: string
  entrada: string | null
}

export interface EtapaKanban {
  id: number
  nome: string
  ordem: number
  lotes: LoteKanban[]
}

export async function getKanban(signal?: AbortSignal): Promise<EtapaKanban[]> {
  const res = await apiClient.get<ApiEnvelope<EtapaKanban[]>>('/kanban', { signal })
  return res.data.data
}
