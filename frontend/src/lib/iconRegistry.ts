import {
  LayoutDashboard,
  Cpu,
  Users,
  ClipboardList,
  PauseCircle,
  Clock,
  FileBarChart,
  LayoutGrid,
  History,
  ShieldCheck,
  UserCircle,
  Settings,
  Package,
  Boxes,
  Wrench,
  FileText,
  BarChart3,
  Bell,
  Circle,
  type LucideIcon,
} from 'lucide-react'

export const ICON_REGISTRY: Record<string, LucideIcon> = {
  LayoutDashboard,
  Cpu,
  Users,
  ClipboardList,
  PauseCircle,
  Clock,
  FileBarChart,
  LayoutGrid,
  History,
  ShieldCheck,
  UserCircle,
  Settings,
  Package,
  Boxes,
  Wrench,
  FileText,
  BarChart3,
  Bell,
  Circle,
}

export interface IconOption {
  value: string
  Icon: LucideIcon
}

export const ICON_OPTIONS: IconOption[] = Object.entries(ICON_REGISTRY).map(([value, Icon]) => ({
  value,
  Icon,
}))

export function getIcon(name: string): LucideIcon {
  return ICON_REGISTRY[name] ?? Circle
}
