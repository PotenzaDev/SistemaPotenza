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
  dias_com_movimentacao: number
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

export type TimelineTipoSegmento = 'setup' | 'producao' | 'pausa' | 'parado'

export interface TimelineSegmento {
  tipo: TimelineTipoSegmento
  inicio: string
  fim: string
  motivo: string | null
}

export interface TimelineMaquina {
  maquina_id: number
  maquina: string
  grupo: GrupoRelatorio | null
  segmentos: TimelineSegmento[]
}

export interface TimelineTurno {
  hora_inicio: string
  hora_fim: string
  intervalo_inicio: string | null
  intervalo_fim: string | null
}

export interface TimelineMaquinasResponse {
  turno: TimelineTurno | null
  maquinas: TimelineMaquina[]
}

export interface TimelineMaquinasFiltros {
  data: string
  grupoId?: number
  maquinaId?: number
}

export async function getTimelineMaquinas(
  filtros: TimelineMaquinasFiltros,
  signal?: AbortSignal,
): Promise<TimelineMaquinasResponse> {
  const res = await apiClient.get<ApiEnvelope<TimelineMaquinasResponse>>('/admin/relatorio-timeline-maquinas', {
    signal,
    params: {
      data:       filtros.data,
      grupo_id:   filtros.grupoId,
      maquina_id: filtros.maquinaId,
    },
  })
  return res.data.data
}

export type TipoSegmentoApontamento = 'setup' | 'producao' | 'pausa'

export interface LinhaRelatorioApontamento {
  data: string
  maquina_id: number
  maquina: string
  operario_id: number
  usuario: string
  tipo: TipoSegmentoApontamento
  motivo_pausa: string | null
  inicio: string
  fim: string
  duracao_segundos: number
}

export interface OperarioFiltro {
  id: number
  nome: string
}

export interface FiltrosRelatorioApontamentos {
  grupos: GrupoRelatorio[]
  maquinas: MaquinaFiltro[]
  operarios: OperarioFiltro[]
}

export interface RelatorioApontamentosFiltros {
  dataInicio: string
  dataFim: string
  grupoId?: number
  maquinaId?: number
  operarioId?: number
}

function paramsRelatorioApontamentos(filtros: RelatorioApontamentosFiltros) {
  return {
    data_inicio: filtros.dataInicio,
    data_fim:    filtros.dataFim,
    grupo_id:    filtros.grupoId,
    maquina_id:  filtros.maquinaId,
    operario_id: filtros.operarioId,
  }
}

export async function getRelatorioApontamentos(
  filtros: RelatorioApontamentosFiltros,
  signal?: AbortSignal,
): Promise<LinhaRelatorioApontamento[]> {
  const res = await apiClient.get<ApiEnvelope<LinhaRelatorioApontamento[]>>('/admin/relatorio-apontamentos', {
    signal,
    params: paramsRelatorioApontamentos(filtros),
  })
  return res.data.data
}

export async function getFiltrosRelatorioApontamentos(signal?: AbortSignal): Promise<FiltrosRelatorioApontamentos> {
  const res = await apiClient.get<ApiEnvelope<FiltrosRelatorioApontamentos>>('/admin/relatorio-apontamentos/filtros', { signal })
  return res.data.data
}

export async function baixarRelatorioApontamentosXlsx(filtros: RelatorioApontamentosFiltros): Promise<Blob> {
  const res = await apiClient.get('/admin/relatorio-apontamentos/export', {
    params: paramsRelatorioApontamentos(filtros),
    responseType: 'blob',
  })
  return res.data
}
