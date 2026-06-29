import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm, Controller } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Eye, EyeOff, Loader2, AlertCircle, ScanLine } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { ClearableInput } from '@/components/ui/ClearableInput'
import { useAuth } from '@/hooks/useAuth'
import type { LoginResponse } from '@/api/auth'

const loginSchema = z.object({
  email: z.string().min(1, 'E-mail obrigatório').email('E-mail inválido'),
  password: z.string().min(1, 'Senha obrigatória'),
})

type LoginFormData = z.infer<typeof loginSchema>

export function LoginPage() {
  const navigate = useNavigate()
  const { signIn, signInCracha, isLoading, error } = useAuth()
  const [showPassword, setShowPassword] = useState(false)
  const [matricula, setMatricula] = useState('')
  const crachaRef = useRef<HTMLInputElement>(null)

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({ resolver: zodResolver(loginSchema), defaultValues: { email: '', password: '' } })

  useEffect(() => {
    crachaRef.current?.focus()
  }, [])

  function redirectAfterLogin(result: LoginResponse) {
    if (result.requires_password_change) {
      navigate('/change-password')
    } else if (result.user.role === 'operario') {
      navigate('/operario')
    } else {
      navigate('/admin')
    }
  }

  async function onSubmit(data: LoginFormData) {
    try {
      redirectAfterLogin(await signIn(data))
    } catch {
      // erro tratado pelo hook
    }
  }

  async function handleCrachaSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!matricula.trim()) return
    try {
      redirectAfterLogin(await signInCracha(matricula.trim()))
    } catch {
      setMatricula('')
      crachaRef.current?.focus()
    }
  }

  return (
    <div className="relative min-h-screen flex items-center justify-center p-4 overflow-hidden">

      {/* Fundo: foto da fábrica com overlay escuro */}
      <div
        className="absolute inset-0 bg-cover bg-center bg-no-repeat"
        style={{ backgroundImage: "url('/fabrica.jpeg')" }}
      />
      {/* Overlay escuro sobre a foto */}
      <div className="absolute inset-0 bg-black/70" />

      {/* Card de login */}
      <div className="relative w-full max-w-sm">
        <div className="bg-black/50 backdrop-blur-md border border-white/10 rounded-2xl shadow-2xl p-8 space-y-6">

          {/* Logo + título */}
          <div className="flex flex-col items-center gap-4">
            <img
              src="/logo.png"
              alt="Potenza"
              className="h-16 w-auto object-contain drop-shadow-lg"
            />
            <p className="text-white/60 text-sm tracking-widest uppercase">
              Sistema de Apontamento
            </p>
          </div>

          {/* Divisor */}
          <div className="border-t border-white/10" />

          {/* Login por crachá */}
          <form onSubmit={handleCrachaSubmit} className="space-y-1.5">
            <Label htmlFor="matricula" className="text-white/70 text-sm">
              Bipe seu crachá
            </Label>
            <ClearableInput
              ref={crachaRef}
              id="matricula"
              type="text"
              value={matricula}
              onChange={setMatricula}
              autoComplete="off"
              placeholder="Bipe o código de barras do crachá"
              className="w-full h-9 px-3 py-1 bg-white/10 border border-white/20 rounded-md text-sm text-white placeholder:text-white/30 focus:outline-none focus-visible:ring-1 focus-visible:ring-[#00aa84] focus-visible:border-[#00aa84] transition-colors font-mono tracking-widest"
              trailingExtra={<ScanLine className="w-4 h-4 text-white/40" />}
            />
          </form>

          {/* Divisor "ou" */}
          <div className="flex items-center gap-3">
            <div className="flex-1 border-t border-white/10" />
            <span className="text-white/30 text-xs uppercase tracking-wider">ou</span>
            <div className="flex-1 border-t border-white/10" />
          </div>

          {/* Formulário e-mail/senha */}
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>

            {/* E-mail */}
            <div className="space-y-1.5">
              <Label htmlFor="email" className="text-white/70 text-sm">
                E-mail
              </Label>
              <Controller
                name="email"
                control={control}
                render={({ field }) => (
                  <ClearableInput
                    id="email"
                    type="email"
                    autoComplete="email"
                    placeholder="seu@email.com"
                    value={field.value}
                    onChange={field.onChange}
                    className="w-full h-9 px-3 py-1 bg-white/10 border border-white/20 rounded-md text-sm text-white placeholder:text-white/30 focus:outline-none focus-visible:ring-1 focus-visible:ring-[#00aa84] focus-visible:border-[#00aa84] transition-colors"
                  />
                )}
              />
              {errors.email && (
                <p className="text-red-400 text-xs flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" />
                  {errors.email.message}
                </p>
              )}
            </div>

            {/* Senha */}
            <div className="space-y-1.5">
              <Label htmlFor="password" className="text-white/70 text-sm">
                Senha
              </Label>
              <Controller
                name="password"
                control={control}
                render={({ field }) => (
                  <ClearableInput
                    id="password"
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="current-password"
                    placeholder="••••••••"
                    value={field.value}
                    onChange={field.onChange}
                    className="w-full h-9 px-3 py-1 bg-white/10 border border-white/20 rounded-md text-sm text-white placeholder:text-white/30 focus:outline-none focus-visible:ring-1 focus-visible:ring-[#00aa84] focus-visible:border-[#00aa84] transition-colors [&::-ms-reveal]:hidden [&::-ms-clear]:hidden"
                    trailingExtra={
                      <button
                        type="button"
                        onClick={() => setShowPassword((v) => !v)}
                        className="text-white/40 hover:text-white/80 transition-colors"
                        aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                      >
                        {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                      </button>
                    }
                  />
                )}
              />
              {errors.password && (
                <p className="text-red-400 text-xs flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" />
                  {errors.password.message}
                </p>
              )}
            </div>

            {/* Erro da API */}
            {error && (
              <div className="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 flex items-start gap-2">
                <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
                <p className="text-red-400 text-sm">{error}</p>
              </div>
            )}

            {/* Botão */}
            <Button
              type="submit"
              disabled={isLoading}
              className="w-full h-10 font-semibold text-white transition-all"
              style={{
                backgroundColor: '#00aa84',
                boxShadow: '0 4px 24px 0 rgba(0,170,132,0.25)',
              }}
              onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = '#009973')}
              onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = '#00aa84')}
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Entrando…
                </>
              ) : (
                'Entrar no Sistema'
              )}
            </Button>
          </form>
        </div>

        {/* Rodapé */}
        <p className="text-center text-white/25 text-xs mt-5">
          © {new Date().getFullYear()} Potenza. Todos os direitos reservados.
        </p>
      </div>
    </div>
  )
}
