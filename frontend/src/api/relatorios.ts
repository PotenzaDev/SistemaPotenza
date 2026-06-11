import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface GrupoRelatorio {
  id: number
  nome: string
}

export interface RelatorioMaquina {
  maquina_id: number
  maquina: string
  grupo: GrupoRelatorio | null
  tempo_turno_segundos: number
  tempo_setup_segundos: number
  tempo_producao_segundos: number
  tempo_parado_segundos: number
  qtd_pecas: number
  percentual_utilizacao: number
}

export interface TotaisRelatorioMaquinas {
  tempo_turno_segundos: number
  tempo_setup_segundos: number
  tempo_producao_segundos: number
  tempo_parado_segundos: number
  qtd_pecas: number
}

export interface RelatorioMaquinasResponse {
  maquinas: RelatorioMaquina[]
  totais: TotaisRelatorioMaquinas
  dias_considerados: number
}

export interface RelatorioMaquinasFiltros {
  dataInicio: string
  dataFim: string
  grupoId?: number
  maquinaId?: number
}

export interface MaquinaFiltro {
  id: number
  nome: string
  etapa_fluxo_id: number
}

export interface FiltrosRelatorioMaquinas {
  grupos: GrupoRelatorio[]
  maquinas: MaquinaFiltro[]
}

export async function getRelatorioProducaoMaquinas(
  filtros: RelatorioMaquinasFiltros,
  signal?: AbortSignal,
): Promise<RelatorioMaquinasResponse> {
  const res = await apiClient.get<ApiEnvelope<RelatorioMaquinasResponse>>('/admin/relatorio-maquinas', {
    signal,
    params: {
      data_inicio: filtros.dataInicio,
      data_fim:    filtros.dataFim,
      grupo_id:    filtros.grupoId,
      maquina_id:  filtros.maquinaId,
    },
  })
  return res.data.data
}

export async function getFiltrosRelatorioMaquinas(signal?: AbortSignal): Promise<FiltrosRelatorioMaquinas> {
  const res = await apiClient.get<ApiEnvelope<FiltrosRelatorioMaquinas>>('/admin/relatorio-maquinas/filtros', { signal })
  return res.data.data
}
