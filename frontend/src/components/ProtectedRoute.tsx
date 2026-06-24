import { type ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'

interface ProtectedRouteProps {
  children: ReactNode
  requiredRole?: string[]
  requiredRotina?: string
}

export function ProtectedRoute({ children, requiredRole, requiredRotina }: ProtectedRouteProps) {
  const { isAuthenticated, user } = useAuth()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (requiredRole && user && !requiredRole.includes(user.role)) {
    return <Navigate to="/login" replace />
  }

  if (requiredRotina && user?.role === 'funcionario' && !user.rotinas?.some((r) => r.slug === requiredRotina)) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}
