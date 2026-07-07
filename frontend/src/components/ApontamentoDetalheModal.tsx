import { useEffect, useState } from 'react'
import axios from 'axios'
import { Loader2, X } from 'lucide-react'
import { getApontamentoDetalhe, type ApontamentoDoDia } from '@/api/apontamentos'
import type { Apontamento, FichaApontamento } from '@/api/apontamento'
import { STATUS_LABEL, fmtDuracao, fmtDataHora } from '@/lib/apontamentoFormat'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

interface Props {
  resumo: ApontamentoDoDia | null
  onClose: () => void
}

const SECTION_TITLE = 'text-xs font-medium text-slate-400 uppercase tracking-wider mb-3'
const FIELD_LABEL   = 'text-xs text-slate-500'
const FIELD_VALUE   = 'text-sm text-white'
const TH = 'px-4 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider'
const TH_RIGHT = `${TH} text-right`
const TD = 'px-4 py-2 text-xs text-slate-300'
const TD_RIGHT = `${TD} text-right`

export function ApontamentoDetalheModal({ resumo, onClose }: Props) {
  const [detalhe, setDetalhe] = useState<Apontamento | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError]     = useState<string | null>(null)

  useEffect(() => {
    if (!resumo) {
      setDetalhe(null)
      setError(null)
      return
    }

    const controller = new AbortController()
    setLoading(true)
    setError(null)

    getApontamentoDetalhe(resumo.id, controller.signal)
      .then(setDetalhe)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar os detalhes do apontamento.')
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })

    return () => controller.abort()
  }, [resumo])

  if (!resumo) return null

  const s = STATUS_LABEL[resumo.status] ?? { label: resumo.status, color: 'text-slate-400' }

  const fichasOrdenadas = [...(detalhe?.fichas ?? [])].sort(
    (a, b) => new Date(b.bipada_at).getTime() - new Date(a.bipada_at).getTime()
  )

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

      <div className="relative z-10 w-full max-w-4xl max-h-[90vh] overflow-y-auto bg-[#0f1923] border border-white/10 rounded-2xl shadow-2xl">

        {/* header */}
        <div className="flex items-start justify-between px-6 py-4 border-b border-white/5">
          <div>
            <h2 className="text-base font-semibold text-white font-mono">{resumo.ordem_lote}</h2>
            <p className="text-sm text-slate-400">{resumo.desc_peca ?? resumo.cod_peca}</p>
            <div className="flex items-center gap-3 mt-2 text-xs text-slate-400">
              <span>{resumo.operario ?? '—'}</span>
              <span className="text-slate-600">•</span>
              <span>{resumo.maquina ?? '—'}</span>
              <span className="text-slate-600">•</span>
              <span className={`font-medium ${s.color}`}>{s.label}</span>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <div className="px-6 py-5 space-y-6">
          {loading && (
            <div className="flex items-center justify-center gap-2 py-12 text-slate-400">
              <Loader2 className="w-5 h-5 animate-spin" />
              <span className="text-sm">Carregando…</span>
            </div>
          )}
          {error && (
            <div className="flex items-center justify-center py-12">
              <p className="text-sm text-red-400">{error}</p>
            </div>
          )}

          {!loading && !error && detalhe && (
            <>
              {/* Setup */}
              <section>
                <h3 className={SECTION_TITLE}>Setup</h3>
                <div className="grid grid-cols-3 gap-4">
                  <div>
                    <p className={FIELD_LABEL}>Início</p>
                    <p className={FIELD_VALUE}>{fmtDataHora(detalhe.setup_inicio)}</p>
                  </div>
                  <div>
                    <p className={FIELD_LABEL}>Fim</p>
                    <p className={FIELD_VALUE}>{fmtDataHora(detalhe.setup_fim)}</p>
                  </div>
                  <div>
                    <p className={FIELD_LABEL}>Duração</p>
                    <p className={FIELD_VALUE}>{fmtDuracao(detalhe.setup_duracao_segundos)}</p>
                  </div>
                </div>
              </section>

              {/* Produção */}
              <section>
                <h3 className={SECTION_TITLE}>Produção</h3>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                  <div>
                    <p className={FIELD_LABEL}>Início</p>
                    <p className={FIELD_VALUE}>{fmtDataHora(detalhe.producao_inicio)}</p>
                  </div>
                  <div>
                    <p className={FIELD_LABEL}>Fim</p>
                    <p className={FIELD_VALUE}>{fmtDataHora(detalhe.producao_fim)}</p>
                  </div>
                  <div>
                    <p className={FIELD_LABEL}>Duração</p>
                    <p className={FIELD_VALUE}>{fmtDuracao(detalhe.producao_duracao_segundos)}</p>
                  </div>
                </div>

                {fichasOrdenadas.length > 0 ? (
                  <div className="bg-white/[0.02] border border-white/5 rounded-lg overflow-hidden">
                    <ResponsiveTable
                      columns={[
                        {
                          key: 'pilha',
                          header: 'Pilha',
                          headerClassName: TH,
                          cellClassName: `${TD} text-white`,
                          render: (ficha) => ficha.pilha,
                        },
                        {
                          key: 'produto',
                          header: 'Produto',
                          headerClassName: TH,
                          cellClassName: TD,
                          render: (ficha) => (
                            <>
                              <span className="font-mono text-white">{ficha.cod_peca}</span>
                              {detalhe.desc_peca && (
                                <span className="block text-xs text-slate-500 truncate max-w-[180px]">{detalhe.desc_peca}</span>
                              )}
                            </>
                          ),
                        },
                        {
                          key: 'qtd_peca',
                          header: 'Qtd. Bipada',
                          headerClassName: TH_RIGHT,
                          cellClassName: TD_RIGHT,
                          render: (ficha) => ficha.qtd_peca,
                        },
                        {
                          key: 'qtd_produzida',
                          header: 'Qtd. Produzida',
                          headerClassName: TH_RIGHT,
                          cellClassName: TD_RIGHT,
                          render: (ficha) => ficha.qtd_produzida ?? '—',
                        },
                        {
                          key: 'bipada_at',
                          header: 'Início',
                          headerClassName: TH,
                          cellClassName: TD,
                          render: (ficha) => fmtDataHora(ficha.bipada_at),
                        },
                        {
                          key: 'fim_producao',
                          header: 'Fim',
                          headerClassName: TH,
                          cellClassName: TD,
                          render: (ficha) => fmtDataHora(ficha.fim_producao),
                        },
                      ] satisfies ResponsiveTableColumn<FichaApontamento>[]}
                      data={fichasOrdenadas}
                      keyExtractor={(ficha) => ficha.id}
                    />
                  </div>
                ) : (
                  <p className="text-sm text-slate-500">Nenhuma ficha bipada.</p>
                )}
              </section>

              {/* Pausas */}
              <section>
                <h3 className={SECTION_TITLE}>Pausas</h3>
                {detalhe.pausas.length > 0 ? (
                  <div className="space-y-2">
                    {detalhe.pausas.map(pausa => (
                      <div
                        key={pausa.id}
                        className="flex items-center justify-between gap-4 px-4 py-3 bg-white/[0.02] border border-white/5 rounded-lg text-sm"
                      >
                        <div className="flex items-center gap-2 min-w-0">
                          <span className="text-xs px-2 py-0.5 rounded-full bg-white/5 text-slate-400 uppercase tracking-wider">
                            {pausa.fase === 'setup' ? 'Setup' : 'Produção'}
                          </span>
                          <span className="text-white truncate">{pausa.motivo ?? '—'}</span>
                          {pausa.is_sistema && (
                            <span className="text-xs px-2 py-0.5 rounded-full bg-orange-500/10 text-orange-400 shrink-0">
                              Sistema
                            </span>
                          )}
                        </div>
                        <div className="flex items-center gap-4 text-xs text-slate-400 shrink-0">
                          <span>{fmtDataHora(pausa.inicio)} → {fmtDataHora(pausa.fim)}</span>
                          <span className="text-white font-medium">{fmtDuracao(pausa.duracao_segundos)}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-slate-500">Nenhuma pausa registrada.</p>
                )}
              </section>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
