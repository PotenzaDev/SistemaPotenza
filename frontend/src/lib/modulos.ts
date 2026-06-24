export interface ModuloSistemaOption {
  value: string
  label: string
}

export const MODULOS_SISTEMA: ModuloSistemaOption[] = [
  { value: 'dashboard', label: 'Dashboard' },
  { value: 'maquinas', label: 'Máquinas' },
  { value: 'operarios', label: 'Operários' },
  { value: 'apontamentos', label: 'Apontamentos' },
  { value: 'motivos_pausa', label: 'Motivos de Pausa' },
  { value: 'turnos', label: 'Turnos' },
  { value: 'relatorios', label: 'Relatórios' },
  { value: 'kanban', label: 'Kanban' },
  { value: 'logs', label: 'Logs de Atividade' },
]
