import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Eye, EyeOff, Loader2, AlertCircle, KeyRound } from 'lucide-react'
import { changePassword } from '@/api/auth'
import { useAuth } from '@/hooks/useAuth'
import axios from 'axios'

export function ChangePasswordPage() {
  const navigate = useNavigate()
  const { user, clearPasswordChangeFlag } = useAuth()

  const isFirstAccess = user?.must_change_password ?? false

  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword]         = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [showCurrent, setShowCurrent]         = useState(false)
  const [showNew, setShowNew]                 = useState(false)
  const [loading, setLoading]                 = useState(false)
  const [error, setError]                     = useState<string | null>(null)

  function validate(): string | null {
    if (!isFirstAccess && !currentPassword)  return 'Informe a senha atual.'
    if (!newPassword)                        return 'Informe a nova senha.'
    if (newPassword.length < 6)              return 'A nova senha deve ter pelo menos 6 caracteres.'
    if (!isFirstAccess && newPassword !== confirmPassword) return 'As senhas não coincidem.'
    if (!isFirstAccess && newPassword === currentPassword) return 'A nova senha deve ser diferente da atual.'
    return null
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const validationError = validate()
    if (validationError) { setError(validationError); return }

    setError(null)
    setLoading(true)

    try {
      await changePassword(isFirstAccess ? '' : currentPassword, newPassword)
      clearPasswordChangeFlag()

      if (user?.role === 'operario') {
        navigate('/operario', { replace: true })
      } else {
        navigate('/admin/maquinas', { replace: true })
      }
    } catch (err: unknown) {
      if (axios.isAxiosError(err)) {
        const apiErrors = err.response?.data?.errors as Record<string, string[]> | undefined
        const msg =
          err.response?.data?.message ??
          (apiErrors ? Object.values(apiErrors).flat().join(' ') : null)
        setError(msg || 'Não foi possível alterar a senha.')
      } else {
        setError('Não foi possível alterar a senha.')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="relative min-h-screen flex items-center justify-center p-4 overflow-hidden">

      {/* Fundo */}
      <div
        className="absolute inset-0 bg-cover bg-center bg-no-repeat"
        style={{ backgroundImage: "url('/fabrica.jpeg')" }}
      />
      <div className="absolute inset-0 bg-black/70" />

      <div className="relative w-full max-w-sm">
        <div className="bg-black/50 backdrop-blur-md border border-white/10 rounded-2xl shadow-2xl p-8 space-y-6">

          {/* Logo + título */}
          <div className="flex flex-col items-center gap-4">
            <img
              src="/logo.png"
              alt="Potenza"
              className="h-16 w-auto object-contain drop-shadow-lg"
            />
            <div className="text-center space-y-1">
              <div className="flex items-center justify-center gap-2">
                <KeyRound className="w-4 h-4 text-[#00aa84]" />
                <p className="text-white text-sm font-medium">Troca de Senha Obrigatória</p>
              </div>
              <p className="text-white/50 text-xs">
                {user?.name ? `Olá, ${user.name}. ` : ''}
                Crie uma nova senha para continuar.
              </p>
            </div>
          </div>

          <div className="border-t border-white/10" />

          <form onSubmit={handleSubmit} className="space-y-4" noValidate>

            {/* Senha atual — oculta no primeiro acesso */}
            {!isFirstAccess && (
              <div className="space-y-1.5">
                <label className="text-white/70 text-sm block">Senha atual</label>
                <div className="relative">
                  <input
                    type={showCurrent ? 'text' : 'password'}
                    value={currentPassword}
                    onChange={e => setCurrentPassword(e.target.value)}
                    placeholder="••••••••"
                    autoComplete="current-password"
                    className="w-full px-3 py-2 pr-10 text-sm bg-white/10 border border-white/20 rounded-lg text-white placeholder:text-white/30 focus:outline-none focus:border-[#00aa84] focus:ring-1 focus:ring-[#00aa84] transition-colors"
                  />
                  <button
                    type="button"
                    onClick={() => setShowCurrent(v => !v)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 transition-colors"
                  >
                    {showCurrent ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
              </div>
            )}

            {/* Nova senha */}
            <div className="space-y-1.5">
              <label className="text-white/70 text-sm block">Nova senha</label>
              <div className="relative">
                <input
                  type={showNew ? 'text' : 'password'}
                  value={newPassword}
                  onChange={e => setNewPassword(e.target.value)}
                  placeholder="Mínimo 6 caracteres"
                  autoComplete="new-password"
                  className="w-full px-3 py-2 pr-10 text-sm bg-white/10 border border-white/20 rounded-lg text-white placeholder:text-white/30 focus:outline-none focus:border-[#00aa84] focus:ring-1 focus:ring-[#00aa84] transition-colors"
                />
                <button
                  type="button"
                  onClick={() => setShowNew(v => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 transition-colors"
                >
                  {showNew ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* Confirmar — oculta no primeiro acesso */}
            {!isFirstAccess && (
              <div className="space-y-1.5">
                <label className="text-white/70 text-sm block">Confirmar nova senha</label>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={e => setConfirmPassword(e.target.value)}
                  placeholder="Repita a nova senha"
                  autoComplete="new-password"
                  className="w-full px-3 py-2 text-sm bg-white/10 border border-white/20 rounded-lg text-white placeholder:text-white/30 focus:outline-none focus:border-[#00aa84] focus:ring-1 focus:ring-[#00aa84] transition-colors"
                />
              </div>
            )}

            {/* Erro */}
            {error && (
              <div className="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 flex items-start gap-2">
                <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
                <p className="text-red-400 text-sm">{error}</p>
              </div>
            )}

            <button
              type="submit"
              disabled={loading}
              className="w-full h-10 flex items-center justify-center gap-2 font-semibold text-white rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              style={{ backgroundColor: '#00aa84', boxShadow: '0 4px 24px 0 rgba(0,170,132,0.25)' }}
            >
              {loading
                ? <><Loader2 className="w-4 h-4 animate-spin" />Salvando…</>
                : 'Definir nova senha'}
            </button>
          </form>
        </div>
      </div>
    </div>
  )
}
