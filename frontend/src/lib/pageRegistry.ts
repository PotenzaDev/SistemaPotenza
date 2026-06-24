export interface PageOption {
  value: string
  label: string
}

export const PAGE_REGISTRY: PageOption[] = [
  { value: '/admin/dashboard',                     label: 'Dashboard' },
  { value: '/admin/maquinas',                       label: 'Máquinas' },
  { value: '/admin/operarios',                      label: 'Operários' },
  { value: '/admin/apontamentos',                   label: 'Apontamentos' },
  { value: '/admin/motivos-pausa',                  label: 'Motivos de Pausa' },
  { value: '/admin/turnos',                         label: 'Turnos' },
  { value: '/admin/relatorios',                     label: 'Relatórios' },
  { value: '/admin/relatorios/producao-maquinas',   label: 'Relatório de Produção por Máquina' },
  { value: '/admin/logs',                           label: 'Log de Atividades' },
  { value: '/admin/usuarios',                       label: 'Usuários do Sistema' },
  { value: '/admin/rotinas',                        label: 'Rotinas' },
]
