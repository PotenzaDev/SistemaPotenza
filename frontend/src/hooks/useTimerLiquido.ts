import { useEffect, useState } from 'react'
import type { Pausa } from '@/api/apontamento'

function fmtTimer(s: number): string {
  const h  = Math.floor(s / 3600)
  const m  = Math.floor((s % 3600) / 60)
  const ss = s % 60
  return h > 0
    ? `${h}:${String(m).padStart(2, '0')}:${String(ss).padStart(2, '0')}`
    : `${String(m).padStart(2, '0')}:${String(ss).padStart(2, '0')}`
}

export function useTimerLiquido(
  startIso: string | null,
  pausas: Pausa[],
  fasePausa: 'setup' | 'producao',
): string {
  const [display, setDisplay] = useState('00:00')

  useEffect(() => {
    if (!startIso) { setDisplay('00:00'); return }

    const calcNet = () => {
      const raw = Math.max(0, Math.floor((Date.now() - new Date(startIso).getTime()) / 1000))
      const pausasDaFase = pausas.filter(p => p.fase === fasePausa)
      const fechadasSeg  = pausasDaFase
        .filter(p => p.fim !== null)
        .reduce((sum, p) => sum + (p.duracao_segundos ?? 0), 0)
      const aberta    = pausasDaFase.find(p => p.fim === null)
      const abertaSeg = aberta
        ? Math.floor((Date.now() - new Date(aberta.inicio).getTime()) / 1000)
        : 0
      return Math.max(0, raw - fechadasSeg - abertaSeg)
    }

    setDisplay(fmtTimer(calcNet()))
    const id = setInterval(() => setDisplay(fmtTimer(calcNet())), 1000)
    return () => clearInterval(id)
  }, [startIso, pausas, fasePausa])

  return display
}
