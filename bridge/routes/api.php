<?php

declare(strict_types=1);

use App\Http\Controllers\Api\FichaTecnicaController;
use App\Http\Controllers\Api\ProdutoController;
use Illuminate\Support\Facades\Route;

Route::middleware('verify.bridge.token')->prefix('ficha-tecnica')->name('ficha-tecnica.')->group(function () {
    Route::get('lote', [FichaTecnicaController::class, 'lote'])->name('lote');
    Route::get('pilha', [FichaTecnicaController::class, 'pilha'])->name('pilha');
    Route::get('count-fichas', [FichaTecnicaController::class, 'countFichas'])->name('count-fichas');
    Route::get('lote-variantes', [FichaTecnicaController::class, 'loteVariantes'])->name('lote-variantes');
});

Route::middleware('verify.bridge.token')->prefix('produtos')->name('produtos.')->group(function () {
    Route::get('/', [ProdutoController::class, 'index'])->name('index');
    Route::get('sub-grupos', [ProdutoController::class, 'subGrupos'])->name('sub-grupos');
    Route::get('{codProduto}/pecas', [ProdutoController::class, 'pecas'])->name('pecas');
});
