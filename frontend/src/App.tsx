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

        {/* Área admin/gestor */}
        <Route
          path="/admin"
          element={
            <ProtectedRoute requiredRole={['admin', 'gestor']}>
              <AdminLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Navigate to="dashboard" replace />} />
          <Route path="dashboard"     element={<DashboardPage />} />
          <Route path="maquinas"      element={<MaquinasPage />} />
          <Route path="operarios"     element={<OperariosPage />} />
          <Route path="apontamentos"  element={<ApontamentosPage />} />
          <Route path="motivos-pausa" element={<MotivoPausaPage />} />
          <Route path="turnos"        element={<TurnosPage />} />
          <Route path="relatorios">
            <Route index element={<RelatoriosPage />} />
            <Route path="producao-maquinas" element={<RelatorioProducaoMaquinasPage />} />
          </Route>
          <Route path="perfil" element={<AdminPerfilPage />} />
          <Route path="logs"   element={<ActivityLogPage />} />
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
