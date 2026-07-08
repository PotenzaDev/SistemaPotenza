import type { Apontamento } from '@/api/apontamento'

export type Fase =
  | 'aguardando'
  | 'em_setup'
  | 'em_pausa_setup'
  | 'aguardando_ficha'
  | 'em_pausa_aguardando'
  | 'em_producao'
  | 'em_pausa_producao'
  | 'finalizando'
  | 'concluido'

export function formatDuracao(seg: number | null): string {
  if (seg === null) return '—'
  const h  = Math.floor(seg / 3600)
  const m  = Math.floor((seg % 3600) / 60)
  const s  = seg % 60
  return h > 0
    ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
    : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

export function derivarFase(ap: Apontamento): Fase {
  switch (ap.status) {
    case 'finalizado':          return 'concluido'
    case 'em_producao':         return 'em_producao'
    case 'aguardando_producao': return 'aguardando_ficha'
    case 'em_setup':            return 'em_setup'
    case 'em_pausa_setup':      return 'em_pausa_setup'
    case 'em_pausa_aguardando': return 'em_pausa_aguardando'
    case 'em_pausa_producao':   return 'em_pausa_producao'
    default:                    return 'aguardando'
  }
}

export function mensagemFinalizarTurno(fase: Fase): string {
  switch (fase) {
    case 'em_setup':
    case 'em_producao':
    case 'finalizando':
      return 'O apontamento em andamento será pausado. Você poderá retomá-lo no próximo turno na mesma máquina.'
    case 'em_pausa_setup':
    case 'em_pausa_aguardando':
    case 'em_pausa_producao':
      return 'O apontamento já está pausado e permanecerá assim. Você poderá retomá-lo no próximo turno na mesma máquina.'
    case 'aguardando_ficha':
      return 'O lote ficará aguardando a bipagem da primeira ficha. Você poderá continuar no próximo turno na mesma máquina.'
    default:
      return 'Sua sessão nesta máquina será encerrada.'
  }
}

export function apiMsg(err: unknown): string {
  return (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    ?? 'Erro inesperado. Tente novamente.'
}

export const STATUS_LABEL: Record<string, { label: string; color: string }> = {
  em_setup:            { label: 'Setup',       color: 'text-blue-400'   },
  aguardando_producao: { label: 'Aguardando',  color: 'text-yellow-400' },
  em_producao:         { label: 'Produção',    color: 'text-[#00aa84]'  },
  em_pausa_setup:      { label: 'Pausa Setup', color: 'text-orange-400' },
  em_pausa_aguardando: { label: 'Pausa',       color: 'text-orange-400' },
  em_pausa_producao:   { label: 'Pausa Prod.', color: 'text-orange-400' },
  finalizado:          { label: 'Finalizado',  color: 'text-slate-400'  },
}

export function fmtDuracao(segundos: number | null): string {
  if (segundos === null) return '—'
  const min = Math.round(segundos / 60)
  if (min < 60) return `${min}min`
  return `${Math.floor(min / 60)}h ${min % 60}min`
}

export function fmtHora(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

export function fmtData(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('pt-BR')
}

export function fmtDataHora(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

export function fmtHoraDate(date: Date): string {
  return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

export function horarioLiberacaoTurno(turno: { hora_fim: string; tolerancia_finalizacao_minutos: number }): Date {
  const [h, m, s] = turno.hora_fim.split(':').map(Number)
  const liberado = new Date()
  liberado.setHours(h, m, s ?? 0, 0)
  liberado.setMinutes(liberado.getMinutes() - turno.tolerancia_finalizacao_minutos)
  return liberado
}
