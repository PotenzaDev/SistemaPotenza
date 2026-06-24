import { type ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'

interface ProtectedRouteProps {
  children: ReactNode
  requiredRole?: string[]
  requiredModulo?: string
}

export function ProtectedRoute({ children, requiredRole, requiredModulo }: ProtectedRouteProps) {
  const { isAuthenticated, user } = useAuth()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (requiredRole && user && !requiredRole.includes(user.role)) {
    return <Navigate to="/login" replace />
  }

  if (requiredModulo && user?.role === 'funcionario' && !user.modulos_permitidos?.includes(requiredModulo)) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}
