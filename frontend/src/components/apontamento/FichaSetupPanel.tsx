import type { FichaCabecote, FichaCabecotePosicao, FichaCabecoteBrocaItem } from '@/api/fichasCabecote'
import { ResponsiveTable, type ResponsiveTableColumn } from '@/components/ui/ResponsiveTable'

const FIELD_LABEL = 'text-xs text-slate-500'
const FIELD_VALUE = 'text-sm font-semibold text-white'
const TH = 'px-3 py-2 text-xs font-medium text-slate-400 uppercase tracking-wider'
const TD = 'px-3 py-2 text-slate-300'
const TD_WHITE = 'px-3 py-2 text-white'
const TD_CAPITALIZE = `${TD} capitalize`

function formatDataBr(iso: string | null): string {
  if (!iso) return '—'
  const [y, m, d] = iso.slice(0, 10).split('-')
  return `${d}/${m}/${y}`
}

const posicoesCabecoteColumns: ResponsiveTableColumn<FichaCabecotePosicao>[] = [
  { key: 'cabecote', header: 'Cabeçote', render: (p) => p.cabecote, headerClassName: TH, cellClassName: TD_WHITE },
  { key: 'sentido', header: 'Sentido', render: (p) => p.sentido, headerClassName: TH, cellClassName: TD_CAPITALIZE },
  { key: 'largura_mm', header: 'Largura (mm)', render: (p) => p.largura_mm, headerClassName: TH, cellClassName: TD },
  { key: 'deslocamento_mm', header: 'Deslocamento (mm)', render: (p) => p.deslocamento_mm, headerClassName: TH, cellClassName: TD },
  { key: 'altura_cabecote_mm', header: 'Altura Cabeçote (mm)', render: (p) => p.altura_cabecote_mm, headerClassName: TH, cellClassName: TD },
  { key: 'obs', header: 'Obs', render: (p) => p.obs ?? '—', headerClassName: TH, cellClassName: TD },
]

const posicoesBrocaColumns: ResponsiveTableColumn<FichaCabecoteBrocaItem>[] = [
  { key: 'cabecote', header: 'Cabeçote', render: (b) => b.cabecote, headerClassName: TH, cellClassName: TD_WHITE },
  { key: 'sentido', header: 'Sentido', render: (b) => b.sentido, headerClassName: TH, cellClassName: TD_CAPITALIZE },
  { key: 'posicao', header: 'Posição', render: (b) => b.posicao, headerClassName: TH, cellClassName: TD },
  { key: 'broca', header: 'Broca', render: (b) => b.broca?.codigo ?? '—', headerClassName: TH, cellClassName: TD },
  { key: 'passante', header: 'Passante / Prof. (mm)', render: (b) => (b.passante ? 'Passante (S)' : `${b.profundidade_mm} mm`), headerClassName: TH, cellClassName: TD },
  { key: 'agregado', header: 'Agregado', render: (b) => b.agregado ?? '—', headerClassName: TH, cellClassName: TD },
  { key: 'obs', header: 'Obs', render: (b) => b.obs ?? '—', headerClassName: TH, cellClassName: TD },
]

interface FichaSetupPanelProps {
  ficha: FichaCabecote
}

export function FichaSetupPanel({ ficha }: FichaSetupPanelProps) {
  return (
    <div className="space-y-4">
      <section className="bg-[#0f1923] border border-white/5 rounded-xl px-5 py-4">
        <h2 className="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">Identificação</h2>
        <div className="grid grid-cols-2 gap-4">
          <div><p className={FIELD_LABEL}>Data</p><p className={FIELD_VALUE}>{formatDataBr(ficha.data)}</p></div>
          <div><p className={FIELD_LABEL}>Quant. Peças/vez</p><p className={FIELD_VALUE}>{ficha.quantidade_pecas_vez ?? '—'}</p></div>
          <div><p className={FIELD_LABEL}>Top Esquerdo (mm)</p><p className={FIELD_VALUE}>{ficha.top_esquerdo_mm ?? '—'}</p></div>
          <div><p className={FIELD_LABEL}>Top Direito (mm)</p><p className={FIELD_VALUE}>{ficha.top_direito_mm ?? '—'}</p></div>
          <div className="col-span-2"><p className={FIELD_LABEL}>Velocidade de Trabalho</p><p className={FIELD_VALUE}>{ficha.velocidade_trabalho ?? '—'}</p></div>
          {ficha.observacao && (
            <div className="col-span-2">
              <p className={FIELD_LABEL}>Observação</p>
              <p className={FIELD_VALUE}>{ficha.observacao}</p>
            </div>
          )}
        </div>
      </section>

      {ficha.posicoes_cabecote.length > 0 && (
        <section className="bg-[#0f1923] border border-white/5 rounded-xl px-5 py-4">
          <h2 className="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">Posições de Cabeçotes</h2>
          <div className="bg-white/[0.02] border border-white/5 rounded-lg overflow-x-auto">
            <ResponsiveTable columns={posicoesCabecoteColumns} data={ficha.posicoes_cabecote} keyExtractor={(p) => p.id} />
          </div>
        </section>
      )}

      {ficha.posicoes_broca.length > 0 && (
        <section className="bg-[#0f1923] border border-white/5 rounded-xl px-5 py-4">
          <h2 className="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">Posição das Brocas</h2>
          <div className="bg-white/[0.02] border border-white/5 rounded-lg overflow-x-auto">
            <ResponsiveTable columns={posicoesBrocaColumns} data={ficha.posicoes_broca} keyExtractor={(b) => b.id} />
          </div>
        </section>
      )}
    </div>
  )
}
