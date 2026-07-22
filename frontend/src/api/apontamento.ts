import { apiClient } from './client'
import type { ApiEnvelope } from './auth'
import type { FichaCabecote } from './fichasCabecote'

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
  cod_produto: string | null
  cor_codigo: string | null
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
    | 'em_pausa_aguardando'
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
  cod_produto: string
  cor_codigo: string
}

export interface BiparFichaPayload {
  cod_peca: string
  ordem_lote: string
  qtd_peca: number
  pilha: number
  cod_produto: string
  cor_codigo: string
  confirmar?: boolean
}

export interface FinalizarPayload {
  fichas: { ficha_id: number; qtd_produzida: number }[]
  confirmarParcial?: boolean
}

export interface ResumoFichasPorCor {
  cod_peca: string
  cod_produto: string
  cor_codigo: string
  cor: string
  qtde_total: number
  qtd_bipada: number
  falta: number
  total_pilhas: number
}

// ── Leitura ───────────────────────────────────────────────────────────────────

export async function getApontamentosAtivos(): Promise<Apontamento[]> {
  try {
    const res = await apiClient.get<ApiEnvelope<Apontamento[]>>('/apontamento/ativos')
    return res.data.data ?? []
  } catch {
    return []
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

/** Ficha de setup (FichaCabecote) da peça deste apontamento — null quando não há ficha cadastrada. */
export async function getFichaSetup(id: number): Promise<FichaCabecote | null> {
  try {
    const res = await apiClient.get<ApiEnvelope<FichaCabecote | null>>(`/apontamento/${id}/ficha-setup`)
    return res.data.data
  } catch {
    return null
  }
}

// ── Fluxo de trabalho ─────────────────────────────────────────────────────────

/** Passo 1: bipar lote → cria apontamento + inicia setup automaticamente */
export async function biparLote(payload: BiparLotePayload): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>('/apontamento/bipar', payload)
  return res.data.data
}

/** Passo 1b: inicia nova passagem do mesmo lote (sem re-scanear, usa cod_peca + ordem_lote do apontamento anterior) */
export async function segundaPassagem(payload: Pick<BiparLotePayload, 'cod_peca' | 'ordem_lote'>): Promise<Apontamento> {
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
  const res = await apiClient.post<ApiEnvelope<Apontamento>>(`/apontamento/${id}/finalizar`, {
    fichas: payload.fichas,
    confirmar_parcial: payload.confirmarParcial,
  })
  return res.data.data
}

/** Passo 4b: finaliza sem bipagem individual de fichas (máquinas com possui_producao=false) */
export async function finalizarApontamentoSemProducao(id: number): Promise<Apontamento> {
  const res = await apiClient.post<ApiEnvelope<Apontamento>>(`/apontamento/${id}/finalizar-sem-producao`)
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
