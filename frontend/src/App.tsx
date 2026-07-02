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
import { RelatorioTimelineMaquinasPage } from '@/pages/RelatorioTimelineMaquinasPage'
import { AdminPerfilPage }          from '@/pages/AdminPerfilPage'
import { ActivityLogPage }          from '@/pages/ActivityLogPage'
import { UsuariosSistemaPage }      from '@/pages/UsuariosSistemaPage'
import { RotinasPage }              from '@/pages/RotinasPage'
import { MaquinasDisponiveisPage }  from '@/pages/MaquinasDisponiveisPage'
import { ApontamentoOperarioPage }  from '@/pages/ApontamentoOperarioPage'
import { ManutencaoPainelPage }        from '@/pages/ManutencaoPainelPage'
import { ChamadasSuportePage }         from '@/pages/ChamadasSuportePage'
import { ManutencaoSolicitarPage }     from '@/pages/ManutencaoSolicitarPage'
import { ManutencaoQrSolicitarPage }   from '@/pages/ManutencaoQrSolicitarPage'
import { AdminLayout }              from '@/layouts/AdminLayout'
import { OperarioLayout }          from '@/layouts/OperarioLayout'
import { ProtectedRoute }          from '@/components/ProtectedRoute'
import { AdminHome }               from '@/components/AdminHome'

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
          <Route index element={<AdminHome />} />
          <Route path="dashboard" element={
            <ProtectedRoute requiredRotina="dashboard"><DashboardPage /></ProtectedRoute>
          } />
          <Route path="maquinas" element={
            <ProtectedRoute requiredRotina="maquinas"><MaquinasPage /></ProtectedRoute>
          } />
          <Route path="operarios" element={
            <ProtectedRoute requiredRotina="operarios"><OperariosPage /></ProtectedRoute>
          } />
          <Route path="apontamentos" element={
            <ProtectedRoute requiredRotina="apontamentos"><ApontamentosPage /></ProtectedRoute>
          } />
          <Route path="motivos-pausa" element={
            <ProtectedRoute requiredRotina="motivos_pausa"><MotivoPausaPage /></ProtectedRoute>
          } />
          <Route path="turnos" element={
            <ProtectedRoute requiredRotina="turnos"><TurnosPage /></ProtectedRoute>
          } />
          <Route path="relatorios" element={
            <ProtectedRoute requiredRotina="relatorios"><RelatoriosPage /></ProtectedRoute>
          } />
          <Route path="relatorios/producao-maquinas" element={
            <ProtectedRoute requiredRotina="relatorios"><RelatorioProducaoMaquinasPage /></ProtectedRoute>
          } />
          <Route path="relatorios/timeline-maquinas" element={
            <ProtectedRoute requiredRotina="relatorios"><RelatorioTimelineMaquinasPage /></ProtectedRoute>
          } />
          <Route path="logs" element={
            <ProtectedRoute requiredRotina="logs"><ActivityLogPage /></ProtectedRoute>
          } />
          <Route path="usuarios" element={
            <ProtectedRoute requiredRole={['admin']}><UsuariosSistemaPage /></ProtectedRoute>
          } />
          <Route path="rotinas" element={
            <ProtectedRoute requiredRole={['admin']}><RotinasPage /></ProtectedRoute>
          } />
          <Route path="manutencao/painel" element={
            <ProtectedRoute requiredRotina="manutencao_painel"><ManutencaoPainelPage /></ProtectedRoute>
          } />
          <Route path="chamadas-suporte" element={
            <ProtectedRoute requiredRotina="chamadas_suporte"><ChamadasSuportePage /></ProtectedRoute>
          } />
          <Route path="sem-acesso" element={
            <div className="flex flex-col items-center justify-center h-full py-32 text-center">
              <p className="text-2xl font-bold text-white mb-2">Sem permissão</p>
              <p className="text-slate-400 text-sm">Você não tem acesso a esta página.<br />Solicite ao administrador para liberar o módulo.</p>
            </div>
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
          <Route path="maquinas"             element={<MaquinasDisponiveisPage />} />
          <Route path="apontamento"          element={<ApontamentoOperarioPage />} />
          <Route path="manutencao/solicitar" element={<ManutencaoSolicitarPage />} />
        </Route>

        {/* Página pública — solicitação de manutenção via QR Code */}
        <Route path="/solicitar-manutencao/:maquinaId" element={<ManutencaoQrSolicitarPage />} />

        {/* Raiz e fallback */}
        <Route path="/"  element={<Navigate to="/login" replace />} />
        <Route path="*"  element={<Navigate to="/login" replace />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
