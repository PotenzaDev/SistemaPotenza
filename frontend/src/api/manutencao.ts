import axios from 'axios'
import { apiClient } from './client'
import type { ApiEnvelope } from './auth'

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

const publicClient = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

// Em axios v1.x, setContentType(undefined) é no-op — o default
// 'application/json' vaza para requisições multipart, fazendo o
// backend receber Content-Type errado e rejeitar o upload com 422.
// Removemos explicitamente para o browser colocar o boundary correto.
publicClient.interceptors.request.use((config) => {
  if (config.data instanceof FormData) {
    config.headers.delete('Content-Type')
  }
  return config
})

export type StatusOrdem = 'aberta' | 'em_atendimento' | 'pausada' | 'concluida' | 'cancelada'
export type Prioridade = 'critica' | 'alta' | 'normal' | 'baixa'

export interface PecaOrdemManutencao {
  id: number
  descricao: string
  quantidade: number
  preco_unitario: number
}

export interface ServicoOrdemManutencao {
  id: number
  servico: string
  descricao: string | null
  valor: number
  data: string
}

export interface OrdemManutencao {
  id: number
  maquina: { id: number; nome: string; etapa_fluxo: { id: number; nome: string } | null }
  solicitante: string
  motivo: string
  prioridade: Prioridade
  status: StatusOrdem
  observacoes: string | null
  solicitado_em: string
  atendido_em: string | null
  concluido_em: string | null
  pecas: PecaOrdemManutencao[]
  servicos: ServicoOrdemManutencao[]
  fotos: { id: number; url: string }[]
}

export interface CriarOrdemData {
  maquina_id: number
  solicitante: string
  motivo: string
  prioridade: Prioridade
}

export async function getOrdensManutencao(
  params?: Record<string, string>,
  signal?: AbortSignal,
): Promise<OrdemManutencao[]> {
  const res = await apiClient.get<ApiEnvelope<OrdemManutencao[]>>('/manutencao/admin', { params, signal })
  return res.data.data
}

export async function getOrdensAbertas(signal?: AbortSignal): Promise<OrdemManutencao[]> {
  const res = await apiClient.get<ApiEnvelope<OrdemManutencao[]>>('/manutencao', { signal })
  return res.data.data
}

export async function criarOrdem(data: CriarOrdemData, fotos: File[] = []): Promise<OrdemManutencao> {
  const payload = fotos.length > 0 ? paraFormData(data, fotos) : data
  const res = await apiClient.post<ApiEnvelope<OrdemManutencao>>('/manutencao/solicitar', payload)
  return res.data.data
}

function paraFormData(data: object, fotos: File[]): FormData {
  const formData = new FormData()
  Object.entries(data).forEach(([key, value]) => formData.append(key, String(value)))
  fotos.forEach(foto => formData.append('fotos[]', foto))
  return formData
}

export async function criarOrdemAdmin(data: CriarOrdemData): Promise<OrdemManutencao> {
  const res = await apiClient.post<ApiEnvelope<OrdemManutencao>>('/manutencao/admin', data)
  return res.data.data
}

export async function atualizarStatusOrdem(id: number, status: StatusOrdem): Promise<OrdemManutencao> {
  const res = await apiClient.put<ApiEnvelope<OrdemManutencao>>(`/manutencao/admin/${id}`, { status })
  return res.data.data
}

export async function updateOrdemObservacoes(id: number, observacoes: string): Promise<OrdemManutencao> {
  const res = await apiClient.put<ApiEnvelope<OrdemManutencao>>(`/manutencao/admin/${id}`, { observacoes })
  return res.data.data
}

export async function adicionarPeca(
  ordemId: number,
  data: { descricao: string; quantidade: number; preco_unitario: number },
): Promise<OrdemManutencao> {
  const res = await apiClient.post<ApiEnvelope<OrdemManutencao>>(`/manutencao/admin/${ordemId}/pecas`, data)
  return res.data.data
}

export async function removerPeca(ordemId: number, pecaId: number): Promise<OrdemManutencao> {
  const res = await apiClient.delete<ApiEnvelope<OrdemManutencao>>(`/manutencao/admin/${ordemId}/pecas/${pecaId}`)
  return res.data.data
}

export async function adicionarServico(
  ordemId: number,
  data: { servico: string; descricao: string; valor: number; data: string },
): Promise<OrdemManutencao> {
  const res = await apiClient.post<ApiEnvelope<OrdemManutencao>>(`/manutencao/admin/${ordemId}/servicos`, data)
  return res.data.data
}

export async function removerServico(ordemId: number, servicoId: number): Promise<OrdemManutencao> {
  const res = await apiClient.delete<ApiEnvelope<OrdemManutencao>>(`/manutencao/admin/${ordemId}/servicos/${servicoId}`)
  return res.data.data
}

export interface MaquinaPublica {
  id: number
  nome: string
  codigo: string | null
}

export type PrioridadeQr = 'baixa' | 'normal' | 'alta' | 'critica'

export async function getMaquinaPublica(id: number, signal?: AbortSignal): Promise<MaquinaPublica> {
  const res = await publicClient.get<ApiEnvelope<MaquinaPublica>>(`/publica/maquina/${id}`, { signal })
  return res.data.data
}

export async function criarOrdemPublica(
  maquinaId: number,
  data: { solicitante: string; motivo: string; prioridade: PrioridadeQr },
  fotos: File[] = [],
): Promise<OrdemManutencao> {
  const payload = fotos.length > 0 ? paraFormData(data, fotos) : data
  const res = await publicClient.post<ApiEnvelope<OrdemManutencao>>(
    `/publica/manutencao/${maquinaId}/solicitar`,
    payload,
  )
  return res.data.data
}
