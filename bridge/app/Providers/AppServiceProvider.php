<?php

namespace App\Providers;

use App\Services\FichaTecnica\FichaTecnicaService;
use App\Services\FichaTecnica\FichaTecnicaServiceInterface;
use App\Services\Produto\ProdutoService;
use App\Services\Produto\ProdutoServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FichaTecnicaServiceInterface::class, FichaTecnicaService::class);
        $this->app->bind(ProdutoServiceInterface::class, ProdutoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
