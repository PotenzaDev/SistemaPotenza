import type { ReactNode } from 'react'

export interface ResponsiveTableColumn<T> {
  key: string
  header: string
  render: (row: T) => ReactNode
  headerClassName?: string
  cellClassName?: string
}

interface ResponsiveTableProps<T> {
  columns: ResponsiveTableColumn<T>[]
  data: T[]
  keyExtractor: (row: T) => string | number
  renderActions?: (row: T) => ReactNode
}

const DEFAULT_HEADER_CLASS = 'px-4 py-3 text-xs font-medium text-slate-400 uppercase tracking-wider'
const DEFAULT_CELL_CLASS = 'px-4 py-3 text-slate-300'

export function ResponsiveTable<T>({ columns, data, keyExtractor, renderActions }: ResponsiveTableProps<T>) {
  return (
    <>
      {/* Tabela — telas md e acima */}
      <table className="hidden md:table w-full text-sm">
        <thead>
          <tr className="border-b border-white/5 text-left">
            {columns.map((col) => (
              <th key={col.key} className={col.headerClassName ?? DEFAULT_HEADER_CLASS}>
                {col.header}
              </th>
            ))}
            {renderActions && <th className="px-4 py-3 w-px" />}
          </tr>
        </thead>
        <tbody className="divide-y divide-white/5">
          {data.map((row) => (
            <tr key={keyExtractor(row)} className="hover:bg-white/[0.02] transition-colors">
              {columns.map((col) => (
                <td key={col.key} className={col.cellClassName ?? DEFAULT_CELL_CLASS}>
                  {col.render(row)}
                </td>
              ))}
              {renderActions && (
                <td className="px-4 py-3">
                  <div className="flex items-center justify-end gap-1">{renderActions(row)}</div>
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>

      {/* Cards empilhados — abaixo de md */}
      <div className="md:hidden divide-y divide-white/5">
        {data.map((row) => (
          <div key={keyExtractor(row)} className="px-4 py-3 space-y-1.5">
            {columns.map((col) => (
              <div key={col.key} className="flex items-start justify-between gap-3 text-sm">
                <span className="text-xs font-medium text-slate-500 uppercase tracking-wide shrink-0 pt-0.5">
                  {col.header}
                </span>
                <span className="text-slate-300 text-right">{col.render(row)}</span>
              </div>
            ))}
            {renderActions && (
              <div className="flex items-center justify-end gap-1 pt-2 mt-2 border-t border-white/5">
                {renderActions(row)}
              </div>
            )}
          </div>
        ))}
      </div>
    </>
  )
}
