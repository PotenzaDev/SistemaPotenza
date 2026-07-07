import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export type SentidoCabecote = 'inferior' | 'superior' | 'horizontal'

export const SENTIDO_OPTIONS: { value: SentidoCabecote; label: string }[] = [
  { value: 'inferior', label: 'Inferior' },
  { value: 'superior', label: 'Superior' },
  { value: 'horizontal', label: 'Horizontal' },
]

export interface FichaCabecotePosicao {
  id: number
  cabecote: string
  sentido: SentidoCabecote
  largura_mm: number
  deslocamento_mm: number
  altura_cabecote_mm: number
  obs: string | null
}

export interface FichaCabecoteBrocaRef {
  id: number
  codigo: string
}

export interface FichaCabecoteBrocaItem {
  id: number
  cabecote: string
  sentido: SentidoCabecote
  posicao: string
  broca_id: number
  broca?: FichaCabecoteBrocaRef
  passante: boolean
  profundidade_mm: number | null
  agregado: string | null
  obs: string | null
}

export interface FichaCabecoteMaquina {
  id: number
  nome: string
}

export interface FichaCabecoteOperarioUser {
  id: number
  name: string
}

export interface FichaCabecoteOperario {
  id: number
  matricula: string
  user: FichaCabecoteOperarioUser
}

export interface FichaCabecote {
  id: number
  produto_peca_id: number
  maquina_id: number | null
  operario_id: number | null
  data: string | null
  top_esquerdo_mm: number | null
  top_direito_mm: number | null
  quantidade_pecas_vez: number | null
  velocidade_trabalho: number | null
  observacao: string | null
  maquina: FichaCabecoteMaquina | null
  operario: FichaCabecoteOperario | null
  posicoes_cabecote: FichaCabecotePosicao[]
  posicoes_broca: FichaCabecoteBrocaItem[]
  completa: boolean
}

export interface CreateFichaCabecotePosicaoPayload {
  cabecote: string
  sentido: SentidoCabecote
  largura_mm: number
  deslocamento_mm: number
  altura_cabecote_mm: number
  obs?: string | null
}

export interface CreateFichaCabecoteBrocaPayload {
  cabecote: string
  sentido: SentidoCabecote
  posicao: string
  broca_id: number
  passante: boolean
  profundidade_mm?: number | null
  agregado?: string | null
  obs?: string | null
}

export interface CreateFichaCabecotePayload {
  maquina_id: number | null
  operario_id: number | null
  data: string | null
  top_esquerdo_mm: number | null
  top_direito_mm: number | null
  quantidade_pecas_vez: number | null
  velocidade_trabalho: number | null
  observacao?: string | null
  posicoes_cabecote: CreateFichaCabecotePosicaoPayload[]
  posicoes_broca: CreateFichaCabecoteBrocaPayload[]
}

export type UpdateFichaCabecotePayload = CreateFichaCabecotePayload

export async function getFichaCabecote(id: number, signal?: AbortSignal): Promise<FichaCabecote> {
  const res = await apiClient.get<ApiEnvelope<FichaCabecote>>(`/fichas-cabecote/${id}`, { signal })
  return res.data.data
}

export async function createFichaCabecote(
  pecaId: number,
  payload: CreateFichaCabecotePayload,
): Promise<FichaCabecote> {
  const res = await apiClient.post<ApiEnvelope<FichaCabecote>>(
    `/produto-pecas/${pecaId}/fichas-cabecote`,
    payload,
  )
  return res.data.data
}

export async function updateFichaCabecote(
  id: number,
  payload: UpdateFichaCabecotePayload,
): Promise<FichaCabecote> {
  const res = await apiClient.put<ApiEnvelope<FichaCabecote>>(`/fichas-cabecote/${id}`, payload)
  return res.data.data
}

export async function baixarFichaCabecotePdf(id: number): Promise<Blob> {
  const res = await apiClient.get(`/fichas-cabecote/${id}/pdf`, { responseType: 'blob' })
  return res.data
}

export async function baixarFichaCabecoteBrancoPdf(pecaId: number): Promise<Blob> {
  const res = await apiClient.get(`/produto-pecas/${pecaId}/ficha-cabecote-branco/pdf`, {
    responseType: 'blob',
  })
  return res.data
}

export async function baixarFichasCabecoteBrancoPdfLote(pecaIds: number[]): Promise<Blob> {
  const res = await apiClient.get('/produto-pecas/fichas-cabecote-branco/pdf-lote', {
    params: { ids: pecaIds.join(',') },
    responseType: 'blob',
  })
  return res.data
}

export async function baixarFichasCabecotePdfLote(fichaIds: number[]): Promise<Blob> {
  const res = await apiClient.get('/fichas-cabecote/pdf-lote', {
    params: { ids: fichaIds.join(',') },
    responseType: 'blob',
  })
  return res.data
}
