import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

export type EmpresaErp = 'FBM' | 'FBP'

export interface ProdutoPeca {
  id: number
  numero: number
  nome: string
  sub_grupo: string | null
  dimensao: string | null
  material: string | null
  ordem: number
  fichas_cabecote_count?: number
  ultima_ficha_cabecote?: { id: number; completa: boolean } | null
}

export interface Produto {
  id: number
  cod_produto: string
  nome: string
  grupo: string
  sub_grupo: string
  empresa: EmpresaErp
  ativo: boolean
  pecas?: ProdutoPeca[]
  pecas_count?: number
}

export interface ErpProduto {
  cod_produto: string
  nome: string
  grupo: string
  sub_grupo: string
  ja_importado: boolean
}

export interface BuscarProdutosErpParams {
  empresa: EmpresaErp
  nome?: string
  sub_grupo?: string
}

export interface BuscarSubGruposErpParams {
  empresa: EmpresaErp
}

export interface ImportarProdutoPayload {
  cod_produto: string
  nome: string
  grupo: string
  sub_grupo: string
  empresa: EmpresaErp
}

export async function getProdutos(signal?: AbortSignal): Promise<Produto[]> {
  const res = await apiClient.get<ApiEnvelope<Produto[]>>('/produtos', { signal })
  return res.data.data
}

export async function getProduto(id: number, signal?: AbortSignal): Promise<Produto> {
  const res = await apiClient.get<ApiEnvelope<Produto>>(`/produtos/${id}`, { signal })
  return res.data.data
}

export async function deleteProduto(id: number): Promise<Produto> {
  const res = await apiClient.delete<ApiEnvelope<Produto>>(`/produtos/${id}`)
  return res.data.data
}

export async function buscarProdutosErp(
  params: BuscarProdutosErpParams,
  signal?: AbortSignal,
): Promise<ErpProduto[]> {
  const res = await apiClient.get<ApiEnvelope<ErpProduto[]>>('/produtos/buscar-erp', {
    params,
    signal,
  })
  return res.data.data
}

export async function buscarSubGruposErp(
  params: BuscarSubGruposErpParams,
  signal?: AbortSignal,
): Promise<string[]> {
  const res = await apiClient.get<ApiEnvelope<string[]>>('/produtos/sub-grupos-erp', {
    params,
    signal,
  })
  return res.data.data
}

export async function importarProduto(payload: ImportarProdutoPayload): Promise<Produto> {
  const res = await apiClient.post<ApiEnvelope<Produto>>('/produtos/importar', payload)
  return res.data.data
}
