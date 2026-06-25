<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ApontamentoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EtapaFluxoController;
use App\Http\Controllers\Api\KanbanController;
use App\Http\Controllers\Api\MaquinaController;
use App\Http\Controllers\Api\MotivoPausaController;
use App\Http\Controllers\Api\OperarioController;
use App\Http\Controllers\Api\RelatorioMaquinaController;
use App\Http\Controllers\Api\RotinaController;
use App\Http\Controllers\Api\RelatorioTurnoController;
use App\Http\Controllers\Api\SessaoTrabalhoController;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\UsuarioSistemaController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/login-cracha', [AuthController::class, 'loginCracha']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout',          [AuthController::class, 'logout']);
        Route::get('/me',               [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::put('/profile',          [AuthController::class, 'updateProfile']);
    });
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:operario'])->group(function () {
    Route::get('/maquinas/disponiveis',     [SessaoTrabalhoController::class, 'disponiveis']);
    Route::get('/motivos-pausa/disponiveis', [MotivoPausaController::class, 'indexOperario']);

    Route::prefix('sessao')->group(function () {
        Route::post('/iniciar',        [SessaoTrabalhoController::class, 'iniciar']);
        Route::post('/encerrar',       [SessaoTrabalhoController::class, 'encerrar']);
        Route::post('/encerrar-turno', [SessaoTrabalhoController::class, 'encerrarTurno']);
        Route::post('/pausar',         [SessaoTrabalhoController::class, 'pausar']);
        Route::get('/pausadas',        [SessaoTrabalhoController::class, 'pausadas']);
        Route::get('/ativa',           [SessaoTrabalhoController::class, 'ativa']);
        Route::get('/turno-hoje',      [SessaoTrabalhoController::class, 'turnoHoje']);
    });

    Route::prefix('apontamento')->group(function () {
        // Leitura
        Route::get('/ativo',           [ApontamentoController::class, 'ativo']);
        Route::get('/historico',       [ApontamentoController::class, 'historico']);
        Route::get('/fichas/recentes', [ApontamentoController::class, 'fichasRecentes']);
        Route::get('/{id}',            [ApontamentoController::class, 'show']);

        // Fluxo de trabalho
        Route::post('/bipar',                [ApontamentoController::class, 'bipar']);          // 1. bipar lote → cria apontamento + inicia setup
        Route::post('/{id}/finalizar-setup', [ApontamentoController::class, 'finalizarSetup']); // 2. encerra setup → aguardando_producao
        Route::post('/{id}/bipar-ficha',     [ApontamentoController::class, 'biparFicha']);     // 3. bipar ficha → em_producao (repete N vezes)
        Route::post('/{id}/finalizar',       [ApontamentoController::class, 'finalizar']);      // 4. encerra produção + registra qtd por ficha
        // Pausa / retomada
        Route::post('/{id}/pausar',         [ApontamentoController::class, 'pausar']);         // pausa manual com motivo
        Route::post('/{id}/pausar-sistema', [ApontamentoController::class, 'pausarSistema']); // auto-pausa (beacon)
        Route::post('/{id}/retomar',        [ApontamentoController::class, 'retomar']);        // retoma pausa
    });
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:gestor,admin,funcionario'])->group(function () {
    Route::get('/menu', [RotinaController::class, 'menu']);

    Route::get('/admin/dashboard',          [DashboardController::class, 'index'])->middleware('module:dashboard');
    Route::get('/admin/relatorio-turno',    [RelatorioTurnoController::class, 'index'])->middleware('module:relatorios');
    Route::get('/admin/relatorio-maquinas', [RelatorioMaquinaController::class, 'index'])->middleware('module:relatorios');
    Route::get('/admin/relatorio-maquinas/filtros', [RelatorioMaquinaController::class, 'filtros'])->middleware('module:relatorios');
    Route::get('/apontamentos/hoje',     [ApontamentoController::class, 'doDia'])->middleware('module:apontamentos');
    Route::get('/apontamentos/{id}',     [ApontamentoController::class, 'show'])->middleware('module:apontamentos');
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:gestor,admin,funcionario', 'module:kanban'])->prefix('kanban')->group(function () {
    Route::get('/',                           [KanbanController::class, 'index']);
    Route::get('/{etapaFluxoId}/lotes',       [KanbanController::class, 'lotesEtapa']);
    Route::get('/lote/{ordemLote}/historico', [KanbanController::class, 'historicoLote']);
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:admin'])->group(function () {
    Route::apiResource('etapas-fluxo', EtapaFluxoController::class);
    Route::apiResource('usuarios',     UsuarioSistemaController::class);
    Route::apiResource('rotinas',      RotinaController::class);
});

Route::middleware(['auth:sanctum', 'check_password_change', 'role:admin,funcionario'])->group(function () {
    Route::apiResource('maquinas',      MaquinaController::class)->middleware('module:maquinas');
    Route::apiResource('operarios',     OperarioController::class)->middleware('module:operarios');
    Route::apiResource('motivos-pausa', MotivoPausaController::class)->except(['show'])->middleware('module:motivos_pausa');

    Route::get('/turnos',             [TurnoController::class, 'index'])->middleware('module:turnos');
    Route::put('/turnos/{diaSemana}', [TurnoController::class, 'update'])->middleware('module:turnos');

    Route::get('/admin/activity-logs', [ActivityLogController::class, 'index'])->middleware('module:logs');
});
