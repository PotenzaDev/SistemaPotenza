import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface DashboardKpis {
  pecas_hoje: number
  apontamentos_finalizados_hoje: number
  maquinas_ativas: number
  total_pausa_minutos_hoje: number
}

export interface MaquinaDashboard {
  id: number
  nome: string
  status: 'livre' | 'em_setup' | 'aguardando_producao' | 'em_producao' | 'em_pausa_setup' | 'em_pausa_producao'
  operario: string | null
  lote: string | null
  cod_peca: string | null
  desc_peca: string | null
  qtde_total: number | null
  setup_duracao_min: number | null
  producao_duracao_min: number | null
  total_pausa_min: number | null
  inicio: string | null
}

export interface ProducaoPorHora {
  hora: string
  pecas: number
}

export interface PausaPorMotivo {
  motivo: string
  total_min: number
}

export interface DashboardData {
  kpis: DashboardKpis
  maquinas: MaquinaDashboard[]
  producao_por_hora: ProducaoPorHora[]
  pausas_por_motivo: PausaPorMotivo[]
}

export async function getDashboard(): Promise<DashboardData | null> {
  try {
    const res = await apiClient.get<ApiEnvelope<DashboardData>>('/admin/dashboard')
    return res.data.data ?? null
  } catch {
    return null
  }
}
