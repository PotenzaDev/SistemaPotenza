import { useState } from 'react'
import { Eye, EyeOff, Loader2, AlertCircle, CheckCircle, User, KeyRound } from 'lucide-react'
import { updateProfile } from '@/api/auth'
import { useAuth } from '@/hooks/useAuth'
import axios from 'axios'

export function AdminPerfilPage() {
  const { user, clearPasswordChangeFlag } = useAuth()

  const [name, setName]                       = useState(user?.name ?? '')
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword]         = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [showCurrent, setShowCurrent]         = useState(false)
  const [showNew, setShowNew]                 = useState(false)
  const [loading, setLoading]                 = useState(false)
  const [error, setError]                     = useState<string | null>(null)
  const [success, setSuccess]                 = useState<string | null>(null)

  function validate(): string | null {
    if (!name.trim())                          return 'O nome não pode estar em branco.'
    if (newPassword && !currentPassword)       return 'Informe a senha atual para criar uma nova.'
    if (newPassword && newPassword.length < 6) return 'A nova senha deve ter pelo menos 6 caracteres.'
    if (newPassword && newPassword !== confirmPassword) return 'As senhas não coincidem.'
    return null
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const err = validate()
    if (err) { setError(err); setSuccess(null); return }

    setError(null)
    setSuccess(null)
    setLoading(true)

    try {
      await updateProfile({
        name:             name.trim(),
        current_password: currentPassword || undefined,
        new_password:     newPassword     || undefined,
      })

      if (newPassword) {
        clearPasswordChangeFlag()
        setCurrentPassword('')
        setNewPassword('')
        setConfirmPassword('')
      }

      setSuccess('Perfil atualizado com sucesso.')
    } catch (err: unknown) {
      if (axios.isAxiosError(err)) {
        const apiErrors = err.response?.data?.errors as Record<string, string[]> | undefined
        const msg = err.response?.data?.message ??
          (apiErrors ? Object.values(apiErrors).flat().join(' ') : null)
        setError(msg || 'Não foi possível atualizar o perfil.')
      } else {
        setError('Não foi possível atualizar o perfil.')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-lg mx-auto space-y-6 p-6">
      <div>
        <h1 className="text-xl font-semibold text-white">Meu Perfil</h1>
        <p className="text-sm text-slate-400 mt-1">Altere seu nome e senha de acesso.</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6" noValidate>

        {/* Dados pessoais */}
        <div className="bg-white/5 border border-white/10 rounded-xl p-5 space-y-4">
          <div className="flex items-center gap-2 text-slate-300 text-sm font-medium">
            <User className="w-4 h-4" />
            Dados pessoais
          </div>

          <div className="space-y-1.5">
            <label className="text-white/70 text-sm block">Nome</label>
            <input
              type="text"
              value={name}
              onChange={e => setName(e.target.value)}
              autoComplete="name"
              className="w-full px-3 py-2 text-sm bg-white/10 border border-white/20 rounded-lg text-white placeholder:text-white/30 focus:outline-none focus:border-[#00aa84] focus:ring-1 focus:ring-[#00aa84] transition-colors"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-white/70 text-sm block">E-mail</label>
            <input
              type="text"
              value={user?.email ?? ''}
              disabled
              className="w-full px-3 py-2 text-sm bg-white/5 border border-white/10 rounded-lg text-white/40 cursor-not-allowed"
            />
          </div>
        </div>

        {/* Trocar senha */}
        <div className="bg-white/5 border border-white/10 rounded-xl p-5 space-y-4">
          <div className="flex items-center gap-2 text-slate-300 text-sm font-medium">
            <KeyRound className="w-4 h-4" />
            Trocar senha <span className="text-white/30 font-normal">(opcional)</span>
          </div>

          <div className="space-y-1.5">
            <label className="text-white/70 text-sm block">Senha atual</label>
            <div className="relative">
              <input
                type={showCurrent ? 'text' : 'password'}
                value={currentPassword}
                onChange={e => setCurrentPassword(e.target.value)}
                placeholder="••••••"
                autoComplete="current-password"
                className="w-full px-3 py-2 pr-10 text-sm bg-white/10 border border-white/20 rounded-lg text-white placeholder:text-white/30 focus:outline-none focus:border-[#00aa84] focus:ring-1 focus:ring-[#00aa84] transition-colors"
              />
              <button type="button" onClick={() => setShowCurrent(v => !v)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 transition-colors">
                {showCurrent ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

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
              <button type="button" onClick={() => setShowNew(v => !v)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 transition-colors">
                {showNew ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

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
        </div>

        {error && (
          <div className="bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3 flex items-start gap-2">
            <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
            <p className="text-xs text-red-400">{error}</p>
          </div>
        )}

        {success && (
          <div className="bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-4 py-3 flex items-start gap-2">
            <CheckCircle className="w-4 h-4 text-emerald-400 mt-0.5 shrink-0" />
            <p className="text-xs text-emerald-400">{success}</p>
          </div>
        )}

        <button
          type="submit"
          disabled={loading}
          className="h-10 px-6 flex items-center gap-2 font-semibold text-sm text-white rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          style={{ backgroundColor: '#00aa84', boxShadow: '0 4px 24px 0 rgba(0,170,132,0.25)' }}
        >
          {loading ? <><Loader2 className="w-4 h-4 animate-spin" />Salvando…</> : 'Salvar alterações'}
        </button>
      </form>
    </div>
  )
}
