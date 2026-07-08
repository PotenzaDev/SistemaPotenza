<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ApontamentoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrocaController;
use App\Http\Controllers\Api\ChamadaSuporteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EtapaFluxoController;
use App\Http\Controllers\Api\FichaCabecoteController;
use App\Http\Controllers\Api\KanbanController;
use App\Http\Controllers\Api\ManutencaoAdminController;
use App\Http\Controllers\Api\ManutencaoPecasController;
use App\Http\Controllers\Api\ManutencaoPublicoController;
use App\Http\Controllers\Api\ManutencaoQrController;
use App\Http\Controllers\Api\ManutencaoServicosController;
use App\Http\Controllers\Api\MaquinaController;
use App\Http\Controllers\Api\MotivoPausaController;
use App\Http\Controllers\Api\OperarioController;
use App\Http\Controllers\Api\ProdutoController;
use App\Http\Controllers\Api\RelatorioMaquinaController;
use App\Http\Controllers\Api\RelatorioTurnoController;
use App\Http\Controllers\Api\RotinaController;
use App\Http\Controllers\Api\SessaoTrabalhoController;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\UsuarioSistemaController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->prefix('publica')->group(function () {
    Route::get('/maquina/{id}', [ManutencaoQrController::class, 'maquina']);
    Route::post('/manutencao/{maquinaId}/solicitar', [ManutencaoQrController::class, 'solicitar']);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/login-cracha', [AuthController::class, 'loginCracha']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:operario'])->group(function () {
    Route::get('/maquinas/disponiveis', [SessaoTrabalhoController::class, 'disponiveis']);
    Route::get('/motivos-pausa/disponiveis', [MotivoPausaController::class, 'indexOperario']);

    Route::prefix('sessao')->group(function () {
        Route::post('/iniciar', [SessaoTrabalhoController::class, 'iniciar']);
        Route::post('/encerrar', [SessaoTrabalhoController::class, 'encerrar']);
        Route::post('/encerrar-turno', [SessaoTrabalhoController::class, 'encerrarTurno']);
        Route::post('/pausar', [SessaoTrabalhoController::class, 'pausar']);
        Route::post('/pausar-ociosa', [SessaoTrabalhoController::class, 'pausarOciosa']);
        Route::post('/retomar-ociosa', [SessaoTrabalhoController::class, 'retomarOciosa']);
        Route::post('/cancelar', [SessaoTrabalhoController::class, 'cancelar']);
        Route::get('/pausadas', [SessaoTrabalhoController::class, 'pausadas']);
        Route::get('/ativa', [SessaoTrabalhoController::class, 'ativa']);
        Route::get('/turno-hoje', [SessaoTrabalhoController::class, 'turnoHoje']);
    });

    Route::prefix('apontamento')->group(function () {
        // Leitura
        Route::get('/ativo', [ApontamentoController::class, 'ativo']);
        Route::get('/historico', [ApontamentoController::class, 'historico']);
        Route::get('/fichas/recentes', [ApontamentoController::class, 'fichasRecentes']);
        Route::get('/{id}/fichas-por-cor', [ApontamentoController::class, 'fichasPorCor']);
        Route::get('/{id}/ficha-setup', [ApontamentoController::class, 'fichaSetup']);
        Route::get('/{id}', [ApontamentoController::class, 'show']);

        // Fluxo de trabalho
        Route::post('/bipar', [ApontamentoController::class, 'bipar']);                  // 1. bipar lote → cria apontamento + inicia setup
        Route::post('/segunda-passagem', [ApontamentoController::class, 'iniciarSegundaPassagem']); // 1b. nova passagem do mesmo lote
        Route::post('/{id}/finalizar-setup', [ApontamentoController::class, 'finalizarSetup']);        // 2. encerra setup → aguardando_producao
        Route::post('/{id}/bipar-ficha', [ApontamentoController::class, 'biparFicha']);            // 3. bipar ficha → em_producao (repete N vezes)
        Route::post('/{id}/finalizar', [ApontamentoController::class, 'finalizar']);             // 4. encerra produção + registra qtd por ficha
        // Pausa / retomada
        Route::post('/{id}/pausar', [ApontamentoController::class, 'pausar']);         // pausa manual com motivo
        Route::post('/{id}/pausar-sistema', [ApontamentoController::class, 'pausarSistema']); // auto-pausa (beacon)
        Route::post('/{id}/retomar', [ApontamentoController::class, 'retomar']);        // retoma pausa
    });

    Route::get('/manutencao', [ManutencaoPublicoController::class, 'index']);
    Route::post('/manutencao/solicitar', [ManutencaoPublicoController::class, 'solicitar']);

    Route::post('/apontamento/chamar-suporte', [ChamadaSuporteController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:gestor,admin,funcionario'])->group(function () {
    Route::get('/menu', [RotinaController::class, 'menu']);

    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->middleware('module:dashboard');
    Route::get('/admin/chamadas-suporte', [ChamadaSuporteController::class, 'index'])->middleware('module:chamadas_suporte');
    Route::put('/admin/chamadas-suporte/{id}/visualizar', [ChamadaSuporteController::class, 'visualizar'])->middleware('module:chamadas_suporte');
    Route::get('/admin/relatorio-turno', [RelatorioTurnoController::class, 'index'])->middleware('module:relatorios');
    Route::get('/admin/relatorio-maquinas', [RelatorioMaquinaController::class, 'index'])->middleware('module:relatorios');
    Route::get('/admin/relatorio-maquinas/filtros', [RelatorioMaquinaController::class, 'filtros'])->middleware('module:relatorios');
    Route::get('/admin/relatorio-timeline-maquinas', [RelatorioMaquinaController::class, 'timeline'])->middleware('module:relatorios');
    Route::get('/apontamentos/hoje', [ApontamentoController::class, 'doDia'])->middleware('module:apontamentos');
    Route::get('/apontamentos/{id}', [ApontamentoController::class, 'show'])->middleware('module:apontamentos');

    Route::prefix('manutencao/admin')->group(function () {
        Route::get('/', [ManutencaoAdminController::class, 'index']);
        Route::post('/', [ManutencaoAdminController::class, 'store']);
        Route::get('/{id}', [ManutencaoAdminController::class, 'show']);
        Route::put('/{id}', [ManutencaoAdminController::class, 'update']);
        Route::post('/{ordemId}/pecas', [ManutencaoPecasController::class, 'store']);
        Route::delete('/{ordemId}/pecas/{pecaId}', [ManutencaoPecasController::class, 'destroy']);
        Route::post('/{ordemId}/servicos', [ManutencaoServicosController::class, 'store']);
        Route::delete('/{ordemId}/servicos/{servicoId}', [ManutencaoServicosController::class, 'destroy']);
    });

    Route::post('/manutencao/admin/chamar-suporte', [ChamadaSuporteController::class, 'storeManutencao']);
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:gestor,admin,funcionario', 'module:kanban'])->prefix('kanban')->group(function () {
    Route::get('/', [KanbanController::class, 'index']);
    Route::get('/{etapaFluxoId}/lotes', [KanbanController::class, 'lotesEtapa']);
    Route::get('/lote/{ordemLote}/historico', [KanbanController::class, 'historicoLote']);
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:admin'])->group(function () {
    Route::apiResource('etapas-fluxo', EtapaFluxoController::class)->except(['index']);
    Route::apiResource('usuarios', UsuarioSistemaController::class);
    Route::apiResource('rotinas', RotinaController::class);
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:admin,funcionario'])->group(function () {
    Route::apiResource('maquinas', MaquinaController::class)->middleware('module:maquinas');
    Route::apiResource('operarios', OperarioController::class)->middleware('module:operarios');
    Route::get('/etapas-fluxo', [EtapaFluxoController::class, 'index'])->middleware('module:operarios');
    Route::apiResource('motivos-pausa', MotivoPausaController::class)->except(['show'])->middleware('module:motivos_pausa');
    Route::apiResource('brocas', BrocaController::class)->except(['show'])->middleware('module:brocas');

    Route::get('/produtos', [ProdutoController::class, 'index'])->middleware('module:produtos');
    Route::get('/produtos/buscar-erp', [ProdutoController::class, 'buscarErp'])->middleware('module:produtos');
    Route::get('/produtos/sub-grupos-erp', [ProdutoController::class, 'subGruposErp'])->middleware('module:produtos');
    Route::post('/produtos/importar', [ProdutoController::class, 'importar'])->middleware('module:produtos');
    Route::get('/produtos/{id}', [ProdutoController::class, 'show'])->middleware('module:produtos');
    Route::delete('/produtos/{id}', [ProdutoController::class, 'destroy'])->middleware('module:produtos');

    Route::get('/produto-pecas/buscar-por-codigo', [ProdutoController::class, 'buscarPecaPorCodigo'])->middleware('module:produtos');
    Route::get('/produto-pecas/{pecaId}/fichas-cabecote', [FichaCabecoteController::class, 'index'])->middleware('module:produtos');
    Route::post('/produto-pecas/{pecaId}/fichas-cabecote', [FichaCabecoteController::class, 'store'])->middleware('module:produtos');
    Route::get('/produto-pecas/{pecaId}/ficha-cabecote-branco/pdf', [FichaCabecoteController::class, 'blankPdf'])->middleware('module:produtos');
    Route::get('/produto-pecas/fichas-cabecote-branco/pdf-lote', [FichaCabecoteController::class, 'blankPdfLote'])->middleware('module:produtos');
    Route::get('/fichas-cabecote/pdf-lote', [FichaCabecoteController::class, 'pdfLote'])->middleware('module:produtos');
    Route::get('/fichas-cabecote/{id}', [FichaCabecoteController::class, 'show'])->middleware('module:produtos');
    Route::put('/fichas-cabecote/{id}', [FichaCabecoteController::class, 'update'])->middleware('module:produtos');
    Route::get('/fichas-cabecote/{id}/pdf', [FichaCabecoteController::class, 'pdf'])->middleware('module:produtos');

    Route::get('/turnos', [TurnoController::class, 'index'])->middleware('module:turnos');
    Route::put('/turnos/{diaSemana}', [TurnoController::class, 'update'])->middleware('module:turnos');

    Route::get('/admin/activity-logs', [ActivityLogController::class, 'index'])->middleware('module:logs');
});
