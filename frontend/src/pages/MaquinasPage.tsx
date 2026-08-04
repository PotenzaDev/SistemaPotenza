import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import axios from 'axios'
import { Cpu, CheckCircle2, XCircle, Loader2, Plus, ImageIcon, Pencil, QrCode, X, Download, Printer } from 'lucide-react'
import { QRCodeSVG } from 'qrcode.react'
import { getMaquinas, type Maquina } from '@/api/maquinas'
import { MaquinaFormModal } from '@/components/MaquinaFormModal'
import { useAuth } from '@/hooks/useAuth'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

type Filtro = 'todos' | 'ativos' | 'inativos'

const FILTROS: { value: Filtro; label: string }[] = [
  { value: 'todos',    label: 'Todos'    },
  { value: 'ativos',   label: 'Ativos'   },
  { value: 'inativos', label: 'Inativos' },
]

const INPUT_CLASS =
  'w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white ' +
  'placeholder:text-slate-600 focus:outline-none focus:border-[#00aa84]/60 focus:bg-[#00aa84]/5 transition-colors'

const maquinaColumns: ResponsiveTableColumn<Maquina>[] = [
  {
    key: 'foto',
    header: '',
    render: (m) =>
      m.foto_url ? (
        <img
          src={m.foto_url}
          alt={m.nome}
          className="w-10 h-10 rounded-lg object-cover border border-white/10"
        />
      ) : (
        <div className="w-10 h-10 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
          <ImageIcon className="w-4 h-4 text-slate-600" />
        </div>
      ),
    headerClassName: 'px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider w-14',
    cellClassName: 'px-4 py-3',
  },
  {
    key: 'nome',
    header: 'Nome',
    render: (m) => m.nome,
    cellClassName: 'px-4 py-3 font-medium text-white',
  },
  { key: 'marca', header: 'Marca', render: (m) => m.marca ?? '—' },
  { key: 'modelo', header: 'Modelo', render: (m) => m.modelo ?? '—' },
  {
    key: 'codigo',
    header: 'Código',
    render: (m) => m.codigo ?? '—',
    cellClassName: 'px-4 py-3 text-slate-400 font-mono text-xs',
  },
  { key: 'ano', header: 'Ano', render: (m) => m.ano ?? '—' },
  { key: 'grupo', header: 'Grupo', render: (m) => m.etapa_fluxo?.nome ?? '—' },
  {
    key: 'status',
    header: 'Status',
    render: (m) =>
      m.ativa ? (
        <span className="inline-flex items-center gap-1.5 text-[#00aa84]">
          <CheckCircle2 className="w-4 h-4" /> Ativa
        </span>
      ) : (
        <span className="inline-flex items-center gap-1.5 text-slate-500">
          <XCircle className="w-4 h-4" /> Inativa
        </span>
      ),
    cellClassName: 'px-4 py-3',
  },
]

interface QrPrintItem {
  nome: string
  url: string
  svg: SVGElement
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function buildQrPrintHtml(title: string, items: QrPrintItem[]): string {
  const pages = items
    .map(
      item => `
        <div class="qr-page">
          <div class="qr-card">
            ${item.svg.outerHTML}
            <div class="qr-info">
              <h2>${escapeHtml(item.nome)}</h2>
              <p>${escapeHtml(item.url)}</p>
            </div>
          </div>
        </div>
      `
    )
    .join('')

  return `
    <html><head><title>${escapeHtml(title)}</title>
    <style>
      @page { size: 100mm 50mm; margin: 0; }
      body { margin:0; font-family: sans-serif; background:#fff; }
      .qr-page {
        width: 100mm; height: 50mm; box-sizing: border-box;
        display:flex; align-items:center; justify-content:center;
        padding: 3mm; page-break-after: always;
      }
      .qr-page:last-child { page-break-after: auto; }
      .qr-card { display:flex; align-items:center; gap:6mm; width:100%; height:100%; }
      .qr-card svg { width:32mm; height:32mm; flex-shrink:0; }
      .qr-info { display:flex; flex-direction:column; justify-content:center; overflow:hidden; }
      h2 { margin:0 0 4px; font-size:14px; color:#111; }
      p { margin:0; font-size:9px; color:#555; word-break:break-all; }
    </style></head>
    <body>${pages}</body></html>
  `
}

export function MaquinasPage() {
  const { user }                        = useAuth()
  const [maquinas, setMaquinas]         = useState<Maquina[]>([])
  const [loading, setLoading]           = useState(true)
  const [error, setError]               = useState<string | null>(null)
  const [filtro, setFiltro]             = useState<Filtro>('ativos')
  const [busca, setBusca]               = useState('')
  const [grupoId, setGrupoId]           = useState<number | ''>('')
  const [modalOpen, setModalOpen]       = useState(false)
  const [editingMaquina, setEditingMaquina] = useState<Maquina | undefined>()

  const canCreate = user?.role === 'admin'

  const [qrMaquina, setQrMaquina] = useState<Maquina | null>(null)
  const qrRef = useRef<HTMLDivElement>(null)

  const [printBatch, setPrintBatch] = useState<Maquina[] | null>(null)
  const batchRef = useRef<HTMLDivElement>(null)

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
    openPrintWindow(`QR - ${qrMaquina.nome}`, [{ nome: qrMaquina.nome, url: qrUrl(qrMaquina), svg }])
  }

  function openPrintWindow(title: string, items: QrPrintItem[]) {
    const win = window.open('', '_blank')
    if (!win) {
      setError('Não foi possível abrir a janela de impressão. Verifique se o bloqueador de pop-ups está desativado.')
      return
    }
    win.document.write(buildQrPrintHtml(title, items))
    win.document.close()
    win.focus()
    win.print()
  }

  function openBatchPrint() {
    if (grupoId === '') return
    if (filtered.length === 0) return
    setPrintBatch(filtered)
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

  useEffect(() => {
    if (!printBatch || printBatch.length === 0) return
    const container = batchRef.current
    if (!container) return

    const items: QrPrintItem[] = []
    printBatch.forEach(m => {
      const card = container.querySelector<HTMLDivElement>(`[data-maquina-id="${m.id}"]`)
      const svg = card?.querySelector('svg')
      if (svg) items.push({ nome: m.nome, url: qrUrl(m), svg })
    })

    if (items.length > 0) {
      const grupoNome = grupos.find(g => g.id === grupoId)?.nome ?? 'Grupo'
      openPrintWindow(`QR Codes - ${grupoNome}`, items)
    }

    setPrintBatch(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [printBatch])

  const grupos = useMemo(() => {
    const mapa = new Map<number, string>()
    for (const m of maquinas) {
      if (m.etapa_fluxo) mapa.set(m.etapa_fluxo.id, m.etapa_fluxo.nome)
    }
    return Array.from(mapa, ([id, nome]) => ({ id, nome })).sort((a, b) => a.nome.localeCompare(b.nome))
  }, [maquinas])

  const filtered = useMemo(() => {
    const buscaNormalizada = busca.trim().toLowerCase()

    return maquinas.filter(m => {
      if (filtro === 'ativos' && !m.ativa) return false
      if (filtro === 'inativos' && m.ativa) return false
      if (grupoId !== '' && m.etapa_fluxo_id !== grupoId) return false
      if (buscaNormalizada) {
        const nomeMatch   = m.nome.toLowerCase().includes(buscaNormalizada)
        const codigoMatch = m.codigo?.toLowerCase().includes(buscaNormalizada) ?? false
        if (!nomeMatch && !codigoMatch) return false
      }
      return true
    })
  }, [maquinas, filtro, busca, grupoId])

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
      <div className="flex flex-wrap items-center gap-3">
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

        <input
          type="text"
          value={busca}
          onChange={e => setBusca(e.target.value)}
          placeholder="Buscar por nome ou código…"
          className={`${INPUT_CLASS} sm:w-64`}
        />

        <select
          value={grupoId}
          onChange={e => setGrupoId(e.target.value ? Number(e.target.value) : '')}
          className={`${INPUT_CLASS} sm:w-48`}
        >
          <option value="">Todos os grupos</option>
          {grupos.map(g => (
            <option key={g.id} value={g.id}>{g.nome}</option>
          ))}
        </select>

        <button
          onClick={openBatchPrint}
          disabled={grupoId === ''}
          title={grupoId === '' ? 'Selecione um grupo para imprimir todos os QR Codes' : 'Imprimir QR Codes de todas as máquinas do grupo'}
          className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-white/5 hover:bg-white/10 disabled:opacity-40 disabled:cursor-not-allowed border border-white/10 rounded-lg transition-colors"
        >
          <Printer className="w-4 h-4" />
          Imprimir QR Codes do Grupo
        </button>
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
              {busca || grupoId !== ''
                ? 'Nenhuma máquina encontrada para os filtros selecionados.'
                : filtro === 'todos'
                  ? 'Nenhuma máquina cadastrada.'
                  : filtro === 'ativos'
                    ? 'Nenhuma máquina ativa.'
                    : 'Nenhuma máquina inativa.'}
            </p>
          </div>
        )}
        {!loading && !error && filtered.length > 0 && (
          <ResponsiveTable
            columns={maquinaColumns}
            data={filtered}
            keyExtractor={(m) => m.id}
            renderActions={(m) => (
              <>
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
              </>
            )}
          />
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

      {/* Renderização oculta para geração dos QR Codes em lote */}
      {printBatch && (
        <div ref={batchRef} style={{ position: 'fixed', left: -9999, top: -9999 }} aria-hidden="true">
          {printBatch.map(m => (
            <div key={m.id} data-maquina-id={m.id}>
              <QRCodeSVG value={qrUrl(m)} size={220} level="M" />
            </div>
          ))}
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
