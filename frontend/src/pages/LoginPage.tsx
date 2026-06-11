import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Eye, EyeOff, Loader2, AlertCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useAuth } from '@/hooks/useAuth'

const loginSchema = z.object({
  email: z.string().min(1, 'E-mail obrigatório').email('E-mail inválido'),
  password: z.string().min(1, 'Senha obrigatória'),
})

type LoginFormData = z.infer<typeof loginSchema>

export function LoginPage() {
  const navigate = useNavigate()
  const { signIn, isLoading, error } = useAuth()
  const [showPassword, setShowPassword] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({ resolver: zodResolver(loginSchema) })

  async function onSubmit(data: LoginFormData) {
    try {
      const result = await signIn(data)
      if (result.requires_password_change) {
        navigate('/change-password')
      } else if (result.user.role === 'admin' || result.user.role === 'gestor') {
        navigate('/admin/maquinas')
      } else {
        navigate('/operario')
      }
    } catch {
      // erro tratado pelo hook
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

          {/* Formulário */}
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>

            {/* E-mail */}
            <div className="space-y-1.5">
              <Label htmlFor="email" className="text-white/70 text-sm">
                E-mail
              </Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                placeholder="seu@email.com"
                className="bg-white/10 border-white/20 text-white placeholder:text-white/30
                           focus-visible:ring-[#00aa84] focus-visible:border-[#00aa84]"
                {...register('email')}
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
              <div className="relative">
                <Input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="••••••••"
                  className="bg-white/10 border-white/20 text-white placeholder:text-white/30
                             focus-visible:ring-[#00aa84] focus-visible:border-[#00aa84] pr-10
                             [&::-ms-reveal]:hidden [&::-ms-clear]:hidden"
                  {...register('password')}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 transition-colors"
                  aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
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
