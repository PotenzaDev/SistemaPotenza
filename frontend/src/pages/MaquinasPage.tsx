import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import axios from 'axios'
import { Cpu, CheckCircle2, XCircle, Loader2, Plus, ImageIcon, Pencil, QrCode, X, Download, Printer } from 'lucide-react'
import { QRCodeSVG } from 'qrcode.react'
import { getMaquinas, type Maquina } from '@/api/maquinas'
import { MaquinaFormModal } from '@/components/MaquinaFormModal'
import { useAuth } from '@/hooks/useAuth'

type Filtro = 'todos' | 'ativos' | 'inativos'

const FILTROS: { value: Filtro; label: string }[] = [
  { value: 'todos',    label: 'Todos'    },
  { value: 'ativos',   label: 'Ativos'   },
  { value: 'inativos', label: 'Inativos' },
]

export function MaquinasPage() {
  const { user }                        = useAuth()
  const [maquinas, setMaquinas]         = useState<Maquina[]>([])
  const [loading, setLoading]           = useState(true)
  const [error, setError]               = useState<string | null>(null)
  const [filtro, setFiltro]             = useState<Filtro>('ativos')
  const [modalOpen, setModalOpen]       = useState(false)
  const [editingMaquina, setEditingMaquina] = useState<Maquina | undefined>()

  const canCreate = user?.role === 'admin'

  const [qrMaquina, setQrMaquina] = useState<Maquina | null>(null)
  const qrRef = useRef<HTMLDivElement>(null)

  function openQr(m: Maquina) { setQrMaquina(m) }
  function closeQr() { setQrMaquina(null) }

  function qrUrl(m: Maquina) {
    return `${window.location.origin}/solicitar-manutencao/${m.id}`
  }

  function downloadQr() {
    if (!qrMaquina || !qrRef.current) return
    const svg = qrRef.current.querySelector('svg')
    if (!svg) return
    const data = new XMLSerializer().serializeToString(svg)
    const blob = new Blob([data], { type: 'image/svg+xml' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `qr-manutencao-${qrMaquina.nome.replace(/\s+/g, '-').toLowerCase()}.svg`
    a.click()
    URL.revokeObjectURL(url)
  }

  function printQr() {
    if (!qrMaquina || !qrRef.current) return
    const svg = qrRef.current.querySelector('svg')
    if (!svg) return
    const win = window.open('', '_blank')
    if (!win) return
    win.document.write(`
      <html><head><title>QR - ${qrMaquina.nome}</title>
      <style>body{margin:0;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;background:#fff}
      h2{margin-bottom:16px;font-size:18px;color:#111}
      p{margin-top:12px;font-size:12px;color:#555;word-break:break-all;text-align:center;max-width:280px}
      </style></head>
      <body>
        <h2>${qrMaquina.nome}</h2>
        ${svg.outerHTML}
        <p>${qrUrl(qrMaquina)}</p>
      </body></html>
    `)
    win.document.close()
    win.focus()
    win.print()
  }

  const load = useCallback((signal?: AbortSignal) => {
    setLoading(true)
    setError(null)
    getMaquinas(signal)
      .then(setMaquinas)
      .catch((err: unknown) => {
        if (!axios.isCancel(err)) setError('Não foi possível carregar as máquinas.')
      })
      .finally(() => {
        if (!signal?.aborted) setLoading(false)
      })
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    load(controller.signal)
    return () => controller.abort()
  }, [load])

  const filtered = useMemo(() => {
    if (filtro === 'ativos')   return maquinas.filter(m => m.ativa)
    if (filtro === 'inativos') return maquinas.filter(m => !m.ativa)
    return maquinas
  }, [maquinas, filtro])

  function openCreate() {
    setEditingMaquina(undefined)
    setModalOpen(true)
  }

  function openEdit(m: Maquina) {
    setEditingMaquina(m)
    setModalOpen(true)
  }

  function handleClose() {
    setModalOpen(false)
    setEditingMaquina(undefined)
  }

  return (
    <div className="space-y-6">

      {/* cabeçalho */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-[#00aa84]/10">
            <Cpu className="w-5 h-5 text-[#00aa84]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white">Máquinas</h1>
            <p className="text-sm text-slate-400">Gerencie as máquinas do sistema</p>
          </div>
        </div>

        {canCreate && (
          <button
            onClick={openCreate}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-[#00aa84] hover:bg-[#00aa84]/90 rounded-lg transition-colors"
          >
            <Plus className="w-4 h-4" />
            Cadastrar
          </button>
        )}
      </div>

      {/* filtros */}
      <div className="flex items-center gap-1 p-1 bg-white/5 rounded-lg w-fit">
        {FILTROS.map(f => (
          <button
            key={f.value}
            onClick={() => setFiltro(f.value)}
            className={`px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
              filtro === f.value
                ? 'bg-[#00aa84] text-white'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* tabela */}
      <div className="bg-[#0f1923] border border-white/5 rounded-xl overflow-hidden">
        {loading && (
          <div className="flex items-center justify-center gap-2 py-16 text-slate-400">
            <Loader2 className="w-5 h-5 animate-spin" />
            <span className="text-sm">Carregando…</span>
          </div>
        )}
        {error && (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-red-400">{error}</p>
          </div>
        )}
        {!loading && !error && filtered.length === 0 && (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-slate-500">
              {filtro === 'todos'
                ? 'Nenhuma máquina cadastrada.'
                : filtro === 'ativos'
                  ? 'Nenhuma máquina ativa.'
                  : 'Nenhuma máquina inativa.'}
            </p>
          </div>
        )}
        {!loading && !error && filtered.length > 0 && (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-white/5 text-left">
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider w-14"></th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Modelo</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Código</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Ano</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Grupo</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
                <th className="px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider w-20"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {filtered.map((m) => (
                <tr key={m.id} className="hover:bg-white/[0.02] transition-colors">
                  <td className="px-4 py-3">
                    {m.foto_url ? (
                      <img
                        src={m.foto_url}
                        alt={m.nome}
                        className="w-10 h-10 rounded-lg object-cover border border-white/10"
                      />
                    ) : (
                      <div className="w-10 h-10 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
                        <ImageIcon className="w-4 h-4 text-slate-600" />
                      </div>
                    )}
                  </td>
                  <td className="px-4 py-3 font-medium text-white">{m.nome}</td>
                  <td className="px-4 py-3 text-slate-400 font-mono text-xs">{m.codigo ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-300">{m.ano ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-300">{m.etapa_fluxo?.nome ?? '—'}</td>
                  <td className="px-4 py-3">
                    {m.ativa ? (
                      <span className="inline-flex items-center gap-1.5 text-[#00aa84]">
                        <CheckCircle2 className="w-4 h-4" /> Ativa
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1.5 text-slate-500">
                        <XCircle className="w-4 h-4" /> Inativa
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => openQr(m)}
                        title="QR Code de manutenção"
                        className="p-1.5 rounded-lg text-slate-400 hover:text-[#00aa84] hover:bg-white/10 transition-colors"
                      >
                        <QrCode className="w-4 h-4" />
                      </button>
                      {canCreate && (
                        <button
                          onClick={() => openEdit(m)}
                          title="Editar"
                          className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                        >
                          <Pencil className="w-4 h-4" />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Modal QR Code */}
      {qrMaquina && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="bg-[#0f1923] border border-white/10 rounded-2xl p-6 w-full max-w-sm space-y-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs text-slate-500 uppercase tracking-wide">QR Code — Manutenção</p>
                <h2 className="text-white font-semibold mt-0.5">{qrMaquina.nome}</h2>
              </div>
              <button onClick={closeQr} className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div ref={qrRef} className="bg-white rounded-xl p-4 flex items-center justify-center">
              <QRCodeSVG value={qrUrl(qrMaquina)} size={220} level="M" />
            </div>

            <p className="text-xs text-slate-500 text-center break-all">{qrUrl(qrMaquina)}</p>

            <div className="flex gap-2">
              <button
                onClick={downloadQr}
                className="flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-medium bg-white/5 hover:bg-white/10 border border-white/10 text-white rounded-xl transition"
              >
                <Download className="w-4 h-4" /> Baixar SVG
              </button>
              <button
                onClick={printQr}
                className="flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-medium bg-[#00aa84] hover:bg-[#009973] text-white rounded-xl transition"
              >
                <Printer className="w-4 h-4" /> Imprimir
              </button>
            </div>
          </div>
        </div>
      )}

      <MaquinaFormModal
        open={modalOpen}
        onClose={handleClose}
        onSuccess={() => load()}
        initialData={editingMaquina}
      />
    </div>
  )
}
