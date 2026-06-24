import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { LoginPage }               from '@/pages/LoginPage'
import { ChangePasswordPage }      from '@/pages/ChangePasswordPage'
import { DashboardPage }           from '@/pages/DashboardPage'
import { MaquinasPage }            from '@/pages/MaquinasPage'
import { OperariosPage }           from '@/pages/OperariosPage'
import { ApontamentosPage }        from '@/pages/ApontamentosPage'
import { MotivoPausaPage }         from '@/pages/MotivoPausaPage'
import { TurnosPage }              from '@/pages/TurnosPage'
import { RelatoriosPage }          from '@/pages/RelatoriosPage'
import { RelatorioProducaoMaquinasPage } from '@/pages/RelatorioProducaoMaquinasPage'
import { AdminPerfilPage }          from '@/pages/AdminPerfilPage'
import { ActivityLogPage }          from '@/pages/ActivityLogPage'
import { UsuariosSistemaPage }      from '@/pages/UsuariosSistemaPage'
import { MaquinasDisponiveisPage }  from '@/pages/MaquinasDisponiveisPage'
import { ApontamentoOperarioPage }  from '@/pages/ApontamentoOperarioPage'
import { AdminLayout }              from '@/layouts/AdminLayout'
import { OperarioLayout }          from '@/layouts/OperarioLayout'
import { ProtectedRoute }          from '@/components/ProtectedRoute'

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />

        {/* Troca de senha obrigatória — qualquer role autenticada */}
        <Route
          path="/change-password"
          element={
            <ProtectedRoute>
              <ChangePasswordPage />
            </ProtectedRoute>
          }
        />

        {/* Área admin/gestor/funcionário */}
        <Route
          path="/admin"
          element={
            <ProtectedRoute requiredRole={['admin', 'gestor', 'funcionario']}>
              <AdminLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Navigate to="dashboard" replace />} />
          <Route path="dashboard" element={
            <ProtectedRoute requiredModulo="dashboard"><DashboardPage /></ProtectedRoute>
          } />
          <Route path="maquinas" element={
            <ProtectedRoute requiredModulo="maquinas"><MaquinasPage /></ProtectedRoute>
          } />
          <Route path="operarios" element={
            <ProtectedRoute requiredModulo="operarios"><OperariosPage /></ProtectedRoute>
          } />
          <Route path="apontamentos" element={
            <ProtectedRoute requiredModulo="apontamentos"><ApontamentosPage /></ProtectedRoute>
          } />
          <Route path="motivos-pausa" element={
            <ProtectedRoute requiredModulo="motivos_pausa"><MotivoPausaPage /></ProtectedRoute>
          } />
          <Route path="turnos" element={
            <ProtectedRoute requiredModulo="turnos"><TurnosPage /></ProtectedRoute>
          } />
          <Route path="relatorios" element={
            <ProtectedRoute requiredModulo="relatorios"><RelatoriosPage /></ProtectedRoute>
          } />
          <Route path="relatorios/producao-maquinas" element={
            <ProtectedRoute requiredModulo="relatorios"><RelatorioProducaoMaquinasPage /></ProtectedRoute>
          } />
          <Route path="logs" element={
            <ProtectedRoute requiredModulo="logs"><ActivityLogPage /></ProtectedRoute>
          } />
          <Route path="usuarios" element={
            <ProtectedRoute requiredRole={['admin']}><UsuariosSistemaPage /></ProtectedRoute>
          } />
          <Route path="perfil" element={<AdminPerfilPage />} />
        </Route>

        {/* Área do operário */}
        <Route
          path="/operario"
          element={
            <ProtectedRoute requiredRole={['operario']}>
              <OperarioLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Navigate to="maquinas" replace />} />
          <Route path="maquinas"     element={<MaquinasDisponiveisPage />} />
          <Route path="apontamento"  element={<ApontamentoOperarioPage />} />
        </Route>

        {/* Raiz e fallback */}
        <Route path="/"  element={<Navigate to="/login" replace />} />
        <Route path="*"  element={<Navigate to="/login" replace />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
