import { createContext, useCallback, useContext, useState, type ReactNode } from 'react'
import { login as apiLogin, logout as apiLogout, type User, type LoginPayload, type LoginResponse } from '@/api/auth'

const TOKEN_KEY = 'potenza_token'
const USER_KEY  = 'potenza_user'

function getStoredUser(): User | null {
  try {
    const raw = localStorage.getItem(USER_KEY)
    return raw ? (JSON.parse(raw) as User) : null
  } catch {
    return null
  }
}

function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

interface AuthContextValue {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
  error: string | null
  signIn: (payload: LoginPayload) => Promise<LoginResponse>
  signOut: () => Promise<void>
  clearPasswordChangeFlag: () => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser]     = useState<User | null>(getStoredUser)
  const [token, setToken]   = useState<string | null>(getStoredToken)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError]         = useState<string | null>(null)

  const isAuthenticated = Boolean(token)

  const signIn = useCallback(async (payload: LoginPayload): Promise<LoginResponse> => {
    setIsLoading(true)
    setError(null)
    try {
      const result = await apiLogin(payload)
      localStorage.setItem(TOKEN_KEY, result.token)
      localStorage.setItem(USER_KEY, JSON.stringify(result.user))
      setToken(result.token)
      setUser(result.user)
      return result
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        'Credenciais inválidas. Verifique e-mail e senha.'
      setError(message)
      throw err
    } finally {
      setIsLoading(false)
    }
  }, [])

  const signOut = useCallback(async () => {
    try {
      await apiLogout()
    } catch {
      // ignora erros no logout — limpa o estado de qualquer forma
    } finally {
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(USER_KEY)
      setToken(null)
      setUser(null)
    }
  }, [])

  const clearPasswordChangeFlag = useCallback(() => {
    if (!user) return
    const updated: User = { ...user, must_change_password: false }
    localStorage.setItem(USER_KEY, JSON.stringify(updated))
    setUser(updated)
  }, [user])

  return (
    <AuthContext.Provider value={{
      user, token, isAuthenticated, isLoading, error,
      signIn, signOut, clearPasswordChangeFlag,
    }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuthContext(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuthContext deve ser usado dentro de <AuthProvider>')
  return ctx
}
