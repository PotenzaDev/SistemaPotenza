import { useEffect, useState } from 'react'
import { Pencil, Loader2, AlertCircle, Check, X } from 'lucide-react'
import { getTurnos, atualizarTurno, type TurnoDia, type AtualizarTurnoData } from '@/api/turnos'

const NOMES_DIA: Record<number, string> = {
  1: 'Segunda-feira',
  2: 'Terça-feira',
  3: 'Quarta-feira',
  4: 'Quinta-feira',
  5: 'Sexta-feira',
  6: 'Sábado',
  7: 'Domingo',
}

function paraInputHora(hora: string | null): string {
  return hora ? hora.slice(0, 5) : ''
}

export function TurnosPage() {
  const [turnos, setTurnos]   = useState<TurnoDia[]>([])
  const [loading, setLoading] = useState(true)
  const [erroApi, setErroApi] = useState<string | null>(null)

  const [editandoDia, setEditandoDia] = useState<number | null>(null)
  const [editForm, setEditForm]       = useState<AtualizarTurnoData>({
    hora_inicio: '08:00',
    hora_fim: '17:00',
    intervalo_inicio: null,
    intervalo_fim: null,
    tolerancia_finalizacao_minutos: 10,
    ativo: true,
  })
  const [salvandoDia, setSalvandoDia] = useState<number | null>(null)

  useEffect(() => { carregarTurnos() }, [])

  async function carregarTurnos() {
    setLoading(true)
    try {
      setTurnos(await getTurnos())
    } catch {
      setErroApi('Erro ao carregar turnos.')
    } finally {
      setLoading(false)
    }
  }

  function iniciarEdicao(turno: TurnoDia) {
    setEditandoDia(turno.dia_semana)
    setEditForm({
      hora_inicio: paraInputHora(turno.hora_inicio) || '08:00',
      hora_fim: paraInputHora(turno.hora_fim) || '17:00',
      intervalo_inicio: paraInputHora(turno.intervalo_inicio) || null,
      intervalo_fim: paraInputHora(turno.intervalo_fim) || null,
      tolerancia_finalizacao_minutos: turno.tolerancia_finalizacao_minutos,
      ativo: turno.ativo,
    })
    setErroApi(null)
  }

  function cancelarEdicao() {
    setEditandoDia(null)
  }

  async function handleSalvar(diaSemana: number) {
    setSalvandoDia(diaSemana); setErroApi(null)
    try {
      const atualizado = await atualizarTurno(diaSemana, editForm)
      setTurnos(prev => prev.map(t => t.dia_semana === diaSemana ? atualizado : t))
      setEditandoDia(null)
    } catch (err) {
      setErroApi(apiMsg(err))
    } finally {
      setSalvandoDia(null)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold text-white">Turnos</h1>
        <p className="text-sm text-slate-500 mt-0.5">
          Configure o horário de trabalho e a tolerância para finalizar o turno em cada dia da semana.
        </p>
      </div>

      {erroApi && (
        <div className="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
          <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
          <p className="text-xs text-red-400">{erroApi}</p>
        </div>
      )}

      <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-16 gap-2 text-slate-400">
            <Loader2 className="w-4 h-4 animate-spin" />
            <span className="text-sm">Carregando…</span>
          </div>
        ) : (
          <div className="divide-y divide-white/5">
            <div className="hidden sm:grid grid-cols-[1.3fr_0.8fr_0.8fr_1.4fr_1fr_80px_80px] gap-4 px-5 py-3 border-b border-white/5">
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Dia</p>
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Início</p>
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Fim</p>
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Almoço</p>
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tolerância (min)</p>
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Status</p>
              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Ações</p>
            </div>

            {turnos.map(turno => {
              const editando = editandoDia === turno.dia_semana
              const salvando = salvandoDia === turno.dia_semana

              return (
                <div
                  key={turno.dia_semana}
                  className="grid grid-cols-2 sm:grid-cols-[1.3fr_0.8fr_0.8fr_1.4fr_1fr_80px_80px] gap-4 items-center px-5 py-3.5"
                >
                  <p className="text-sm font-medium text-white col-span-2 sm:col-span-1">
                    {NOMES_DIA[turno.dia_semana]}
                  </p>

                  {editando ? (
                    <>
                      <input
                        type="time"
                        value={editForm.hora_inicio}
                        onChange={e => setEditForm(f => ({ ...f, hora_inicio: e.target.value }))}
                        className="px-2 py-1 bg-white/5 border border-[#00aa84]/40 rounded-lg text-sm text-white focus:outline-none focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <input
                        type="time"
                        value={editForm.hora_fim}
                        onChange={e => setEditForm(f => ({ ...f, hora_fim: e.target.value }))}
                        className="px-2 py-1 bg-white/5 border border-[#00aa84]/40 rounded-lg text-sm text-white focus:outline-none focus:ring-1 focus:ring-[#00aa84]/30 transition"
                      />
                      <div className="flex items-center gap-1">
                        <input
                          type="time"
                          value={editForm.intervalo_inicio ?? ''}
                          onChange={e => setEditForm(f => ({ ...f, intervalo_inicio: e.target.value || null }))}
                          className="px-2 py-1 bg-white/5 border border-[#00aa84]/40 rounded-lg text-sm text-white focus:outline-none focus:ring-1 focus:ring-[#00aa84]/30 transition w-full"
                        />
                        <span className="text-slate-500 text-xs">–</span>
                        <input
                          type="time"
                          value={editForm.intervalo_fim ?? ''}
                          onChange={e => setEditForm(f => ({ ...f, intervalo_fim: e.target.value || null }))}
                          className="px-2 py-1 bg-white/5 border border-[#00aa84]/40 rounded-lg text-sm text-white focus:outline-none focus:ring-1 focus:ring-[#00aa84]/30 transition w-full"
                        />
                      </div>
                      <input
                        type="number"
                        min={0}
                        max={120}
                        value={editForm.tolerancia_finalizacao_minutos}
                        onChange={e => setEditForm(f => ({ ...f, tolerancia_finalizacao_minutos: Number(e.target.value) }))}
                        className="px-2 py-1 bg-white/5 border border-[#00aa84]/40 rounded-lg text-sm text-white focus:outline-none focus:ring-1 focus:ring-[#00aa84]/30 transition w-full"
                      />
                      <div className="flex justify-center">
                        <button
                          type="button"
                          onClick={() => setEditForm(f => ({ ...f, ativo: !f.ativo }))}
                          className={[
                            'px-2.5 py-0.5 rounded-full text-xs font-medium transition-colors',
                            editForm.ativo
                              ? 'bg-emerald-500/10 text-emerald-400'
                              : 'bg-slate-700/50 text-slate-500',
                          ].join(' ')}
                        >
                          {editForm.ativo ? 'Ativo' : 'Inativo'}
                        </button>
                      </div>
                      <div className="flex items-center gap-1 justify-end">
                        <button
                          type="button"
                          onClick={() => void handleSalvar(turno.dia_semana)}
                          disabled={salvando}
                          className="shrink-0 p-1.5 rounded-lg text-[#00aa84] hover:bg-[#00aa84]/10 disabled:opacity-50 transition-colors"
                        >
                          {salvando ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Check className="w-3.5 h-3.5" />}
                        </button>
                        <button
                          type="button"
                          onClick={cancelarEdicao}
                          disabled={salvando}
                          className="shrink-0 p-1.5 rounded-lg text-slate-400 hover:bg-white/5 transition-colors"
                        >
                          <X className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </>
                  ) : (
                    <>
                      <p className="text-sm text-slate-300">{paraInputHora(turno.hora_inicio) || '—'}</p>
                      <p className="text-sm text-slate-300">{paraInputHora(turno.hora_fim) || '—'}</p>
                      <p className="text-sm text-slate-300">
                        {turno.intervalo_inicio && turno.intervalo_fim
                          ? `${paraInputHora(turno.intervalo_inicio)} – ${paraInputHora(turno.intervalo_fim)}`
                          : '—'}
                      </p>
                      <p className="text-sm text-slate-300">{turno.tolerancia_finalizacao_minutos}</p>
                      <div className="flex justify-center">
                        {turno.ativo ? (
                          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400">
                            Ativo
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700/50 text-slate-500">
                            Inativo
                          </span>
                        )}
                      </div>
                      <div className="flex items-center justify-end">
                        <button
                          type="button"
                          title="Editar turno"
                          onClick={() => iniciarEdicao(turno)}
                          className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors"
                        >
                          <Pencil className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </>
                  )}
                </div>
              )
            })}
          </div>
        )}
      </div>

      <p className="text-xs text-slate-600">
        Dias marcados como <strong className="text-slate-400">Inativo</strong> não permitem que operadores iniciem setup ou produção.
        A tolerância define quantos minutos antes do fim do turno o operador já pode finalizá-lo.
        O intervalo de almoço, quando configurado, não conta como tempo útil de turno nos relatórios — deixe os campos vazios para não ter intervalo.
      </p>
    </div>
  )
}

function apiMsg(err: unknown): string {
  return (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    ?? 'Erro inesperado. Tente novamente.'
}
