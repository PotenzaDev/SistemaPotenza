<?php

declare(strict_types=1);

use App\Http\Controllers\Api\FichaTecnicaController;
use Illuminate\Support\Facades\Route;

Route::middleware('verify.bridge.token')->prefix('ficha-tecnica')->name('ficha-tecnica.')->group(function () {
    Route::get('lote', [FichaTecnicaController::class, 'lote'])->name('lote');
    Route::get('pilha', [FichaTecnicaController::class, 'pilha'])->name('pilha');
    Route::get('count-fichas', [FichaTecnicaController::class, 'countFichas'])->name('count-fichas');
});
