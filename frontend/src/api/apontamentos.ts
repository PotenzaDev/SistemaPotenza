import { apiClient } from './client'
import type { ApiEnvelope } from './auth'
import type { Apontamento } from './apontamento'

export interface ApontamentoDoDia {
  id: number
  cod_peca: string
  ordem_lote: string
  desc_peca: string | null
  status: string
  operario: string | null
  maquina: string | null
  qtd_pecas: number
  qtd_pilhas: number
  tempo_setup_segundos: number | null
  tempo_producao_segundos: number | null
  numero_pausas: number
  setup_inicio: string | null
  setup_fim: string | null
  producao_inicio: string | null
  producao_fim: string | null
  created_at: string | null
}

export interface ApontamentoFiltros {
  dataInicio?: string
  dataFim?: string
  operarioId?: number
  maquinaId?: number
  ordemLote?: string
}

export interface TotaisApontamentosDoDia {
  qtd_pecas: number
  qtd_pilhas: number
}

export interface ApontamentosDoDia {
  apontamentos: ApontamentoDoDia[]
  totais: TotaisApontamentosDoDia
}

export async function getApontamentosDoDia(
  filtros?: ApontamentoFiltros,
  signal?: AbortSignal,
): Promise<ApontamentosDoDia> {
  const res = await apiClient.get<ApiEnvelope<ApontamentosDoDia>>('/apontamentos/hoje', {
    signal,
    params: {
      data_inicio: filtros?.dataInicio,
      data_fim:    filtros?.dataFim,
      operario_id: filtros?.operarioId,
      maquina_id:  filtros?.maquinaId,
      ordem_lote:  filtros?.ordemLote || undefined,
    },
  })
  return res.data.data
}

export async function getApontamentoDetalhe(
  id: number,
  filtros?: Pick<ApontamentoFiltros, 'dataInicio' | 'dataFim'>,
  signal?: AbortSignal,
): Promise<Apontamento> {
  const res = await apiClient.get<ApiEnvelope<Apontamento>>(`/apontamentos/${id}`, {
    signal,
    params: {
      data_inicio: filtros?.dataInicio,
      data_fim:    filtros?.dataFim,
    },
  })
  return res.data.data
}
