import { fmtHora } from '@/lib/apontamentoFormat'
import type { TimelineSegmento, TimelineTipoSegmento, TimelineTurno } from '@/api/relatorios'

interface Props {
  turno: TimelineTurno
  segmentos: TimelineSegmento[]
  isHoje: boolean
}

const COR_SEGMENTO: Record<TimelineTipoSegmento, string> = {
  setup:    '#3b82f6',
  producao: '#00aa84',
  pausa:    '#f97316',
  parado:   '#ef4444',
}

const LABEL_SEGMENTO: Record<TimelineTipoSegmento, string> = {
  setup:    'Setup',
  producao: 'Produção',
  pausa:    'Pausa',
  parado:   'Parado',
}

function horaParaMinutos(hora: string): number {
  const [h, m] = hora.split(':').map(Number)
  return h * 60 + m
}

function isoParaMinutosDoDia(iso: string): number {
  const data = new Date(iso)
  return data.getHours() * 60 + data.getMinutes() + data.getSeconds() / 60
}

function fmtDuracaoCurta(segundos: number): string {
  const min = Math.round(segundos / 60)
  if (min < 60) return `${min}min`
  const h = Math.floor(min / 60)
  const resto = min % 60
  return resto > 0 ? `${h}h ${resto}min` : `${h}h`
}

function horaComoData(referencia: Date, hora: string): Date {
  const [h, m, s] = hora.split(':').map(Number)
  const data = new Date(referencia)
  data.setHours(h, m, s ?? 0, 0)
  return data
}

/**
 * Soma a duração dos segmentos por tipo, recortada à janela [hora_inicio,
 * hora_fim] do turno — a mesma janela que a barra exibe. Sem esse recorte,
 * segmentos de hora extra (fora da barra, ver posicao() abaixo) inflariam a
 * soma com tempo que o usuário nunca vê na própria barra.
 */
export function somarDuracaoPorTipo(segmentos: TimelineSegmento[], turno: TimelineTurno): Record<TimelineTipoSegmento, number> {
  const totais: Record<TimelineTipoSegmento, number> = { setup: 0, producao: 0, pausa: 0, parado: 0 }

  for (const segmento of segmentos) {
    const inicio = new Date(segmento.inicio)
    const fim = new Date(segmento.fim)
    const limiteInicio = horaComoData(inicio, turno.hora_inicio)
    const limiteFim = horaComoData(inicio, turno.hora_fim)

    const inicioClip = inicio < limiteInicio ? limiteInicio : inicio
    const fimClip = fim > limiteFim ? limiteFim : fim

    const duracaoSegundos = (fimClip.getTime() - inicioClip.getTime()) / 1000
    totais[segmento.tipo] += Math.max(0, duracaoSegundos)
  }

  return totais
}

export function MaquinaTimelineBar({ turno, segmentos, isHoje }: Props) {
  const inicioMin  = horaParaMinutos(turno.hora_inicio)
  const fimMin     = horaParaMinutos(turno.hora_fim)
  const duracaoMin = fimMin - inicioMin

  if (duracaoMin <= 0) return null

  function posicao(minutos: number): number {
    return Math.min(100, Math.max(0, ((minutos - inicioMin) / duracaoMin) * 100))
  }

  const agora    = new Date()
  const agoraMin = isHoje ? agora.getHours() * 60 + agora.getMinutes() : null

  const horasMarcadas: number[] = []
  for (let h = Math.ceil(inicioMin / 60); h <= Math.floor(fimMin / 60); h++) {
    horasMarcadas.push(h * 60)
  }

  return (
    <div className="space-y-1">
      <div className="relative h-8 rounded-md overflow-hidden bg-white/5 border border-white/10">
        {segmentos.map((segmento, index) => {
          const inicioSeg = isoParaMinutosDoDia(segmento.inicio)
          const fimSeg    = isoParaMinutosDoDia(segmento.fim)
          const esquerda  = posicao(inicioSeg)
          const largura   = Math.max(0, posicao(fimSeg) - esquerda)

          if (largura <= 0) return null

          const duracaoSegundos = (new Date(segmento.fim).getTime() - new Date(segmento.inicio).getTime()) / 1000
          const rotulo = segmento.motivo ? `${LABEL_SEGMENTO[segmento.tipo]} (${segmento.motivo})` : LABEL_SEGMENTO[segmento.tipo]

          return (
            <div
              key={index}
              className="absolute inset-y-0"
              style={{ left: `${esquerda}%`, width: `${largura}%`, backgroundColor: COR_SEGMENTO[segmento.tipo] }}
              title={`${rotulo} · ${fmtHora(segmento.inicio)}–${fmtHora(segmento.fim)} (${fmtDuracaoCurta(duracaoSegundos)})`}
            />
          )
        })}

        {agoraMin !== null && agoraMin >= inicioMin && agoraMin <= fimMin && (
          <div
            className="absolute inset-y-0 w-0.5 bg-white shadow-[0_0_4px_rgba(255,255,255,0.8)]"
            style={{ left: `${posicao(agoraMin)}%` }}
          />
        )}
      </div>

      <div className="relative h-4 text-[10px] text-slate-500">
        {horasMarcadas.map(minutos => (
          <span key={minutos} className="absolute -translate-x-1/2" style={{ left: `${posicao(minutos)}%` }}>
            {String(Math.floor(minutos / 60)).padStart(2, '0')}h
          </span>
        ))}
      </div>
    </div>
  )
}
