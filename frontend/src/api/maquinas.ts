import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface EtapaFluxo {
  id: number
  nome: string
  requer_config_cabecote: boolean
}

export interface ConfiguracaoCabecoteMaquina {
  cabecotes_inferiores: number
  cabecotes_superiores: number
  cabecotes_topo: number
  cabecotes_traseiros: number
  pinos_por_cabecote: number
}

export interface RegraMaquina {
  possui_setup: boolean
  possui_producao: boolean
  permite_multiplas_passagens: boolean
  limite_passagens: number | null
  permite_finalizacao_parcial: boolean
}

export interface Maquina {
  id: number
  nome: string
  codigo: string | null
  ano: number | null
  descricao: string | null
  ativa: boolean
  foto_url: string | null
  etapa_fluxo_id: number
  etapa_fluxo: EtapaFluxo | null
  configuracao_cabecote: ConfiguracaoCabecoteMaquina | null
  regra_maquina: RegraMaquina | null
  tem_sessao_interrompida?: boolean
  tem_sessoes_pausadas?: boolean
}

export async function getMaquinas(signal?: AbortSignal): Promise<Maquina[]> {
  const res = await apiClient.get<ApiEnvelope<Maquina[]>>('/maquinas', { signal })
  return res.data.data
}

/** Máquinas ativas do setor do operário autenticado */
export async function getMaquinasDisponiveis(signal?: AbortSignal): Promise<Maquina[]> {
  const res = await apiClient.get<ApiEnvelope<Maquina[]>>('/maquinas/disponiveis', { signal })
  return res.data.data
}

export async function createMaquina(data: FormData): Promise<Maquina> {
  const res = await apiClient.post<ApiEnvelope<Maquina>>('/maquinas', data)
  return res.data.data
}

// PHP só popula $_FILES em requisições POST, então usamos method spoofing (_method=PATCH)
export async function updateMaquina(id: number, data: FormData): Promise<Maquina> {
  data.append('_method', 'PATCH')
  const res = await apiClient.post<ApiEnvelope<Maquina>>(`/maquinas/${id}`, data)
  return res.data.data
}
