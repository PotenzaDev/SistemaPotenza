import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export interface Pausa {
  id: number
  fase: 'setup' | 'producao'
  motivo_id: number
  motivo: string | null
  is_sistema: boolean
  inicio: string
  fim: string | null
  duracao_segundos: number | null
}

export interface FichaApontamento {
  id: number
  cod_peca: string
  pilha: number
  qtd_peca: number
  qtd_produzida: number | null
  total_pilhas: number
  bipada_at: string
  fim_producao: string | null
  duracao_segundos: number | null
  /** Presente apenas no endpoint fichasRecentes */
  ordem_lote?: string
}

export interface Apontamento {
  id: number
  cod_peca: string
  ordem_lote: string
  desc_peca: string | null
  cod_produto: string | null
  qtde_total: number | null
  numero_passagem: number
  apontamento_origem_id: number | null
  status:
    | 'em_setup'
    | 'aguardando_producao'
    | 'em_producao'
    | 'em_pausa_setup'
    | 'em_pausa_producao'
    | 'finalizado'
  etapa_fluxo: { id: number; nome: string } | null
  setup_inicio: string | null
  setup_fim: string | null
  setup_duracao_segundos: number | null
  producao_inicio: string | null
  producao_fim: string | null
  producao_duracao_segundos: number | null
  fichas: FichaApontamento[]
  pausas: Pausa[]
  created_at: string
}

export interface BiparLotePayload {
  cod_peca: string
  ordem_lote: string
}

export interface BiparFichaPayload {
  cod_peca: string
  ordem_lote: string
  qtd_peca: number
  pilha: number
  confirmar?: boolean
}

export interface FinalizarPayload {
  fichas: { ficha_id: number; qtd_produzida: number }[]
}

export interface ResumoFichasPorCor {
  cod_peca: string
  cor: string
  qtd_fichas: number | null
  qtd_bipadas: number
}

// ── Leitura ───────────────────────────────────────────────────────────────────

export async function getApontamentoAtivo(): Promise<Apontamento | null> {
  try {
    const res = await apiClient.get<ApiEnvelope<Apontamento>>('/apontamento/ativo')
    return res.data.data
  } catch {
    return null
  }
}

export async function getFichasRecentes(): Promise<FichaApontamento[]> {
  try {
    const res = await apiClient.get<ApiEnvelope<FichaApontamento[]>>('/apontamento/fichas/recentes')
    return res.data.data ?? []
  } catch {
    return []
  }
}

/** Resumo por cor/variante das fichas já bipadas — vazio quando há só uma cor. */
export async function getFichasPorCor(id: number): Promise<ResumoFichasPorCor[]> {
  try {
    const res = await apiClient.get<ApiEnvelope<ResumoFichasPorCor[]>>(`/apontamento/${id}/fichas-por-cor`)
    return res.data.data ?? []
  } catch {
    return []
  }
}

// ── Fluxo de trabalho ─────────────────────────────────────────────────────────

/** Passo 1: bipar lote → cria apontamento + inicia setup automaticamente */
export async function biparLote(payload: BiparLotePayload): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>('/apontamento/bipar', payload)
  return res.data.data
}

/** Passo 1b: inicia nova passagem do mesmo lote (sem re-scanear, usa cod_peca + ordem_lote do apontamento anterior) */
export async function segundaPassagem(payload: BiparLotePayload): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>('/apontamento/segunda-passagem', payload)
  return res.data.data
}

/** Passo 2: finalizar setup → status aguardando_producao */
export async function finalizarSetup(id: number): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>(`/apontamento/${id}/finalizar-setup`)
  return res.data.data
}

/** Passo 3: bipar ficha de produção — repete N vezes (mesmo lote + produto) */
export async function biparFicha(id: number, payload: BiparFichaPayload): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>(`/apontamento/${id}/bipar-ficha`, payload)
  return res.data.data
}

/** Passo 4: finalizar produção com qtd_produzida por ficha */
export async function finalizarApontamento(id: number, payload: FinalizarPayload): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>(`/apontamento/${id}/finalizar`, payload)
  return res.data.data
}

// ── Pausa / retomada ──────────────────────────────────────────────────────────

/** Pausa manual: operário informa um motivo predefinido */
export async function pausarApontamento(id: number, motivoPausaId: number): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>(`/apontamento/${id}/pausar`, {
    motivo_pausa_id: motivoPausaId,
  })
  return res.data.data
}

/** Retoma um apontamento pausado */
export async function retomarApontamento(id: number): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>(`/apontamento/${id}/retomar`)
  return res.data.data
}

/**
 * Auto-pausa de sistema — fire-and-forget keepalive fetch.
 * Chamado no beforeunload (fechar aba/navegador). Não usa apiClient
 * para garantir keepalive: true, que mantém a requisição viva após
 * o contexto JS ser destruído.
 */
export function pausarSistemaBeacon(id: number): void {
  const token = localStorage.getItem('potenza_token')
  const base  = (import.meta.env.VITE_API_URL as string | undefined) ?? 'http://localhost:8000/api'

  void fetch(`${base}/apontamento/${id}/pausar-sistema`, {
    method:    'POST',
    keepalive: true,
    headers: {
      Authorization:  token ? `Bearer ${token}` : '',
      'Content-Type': 'application/json',
      Accept:         'application/json',
    },
  })
}
