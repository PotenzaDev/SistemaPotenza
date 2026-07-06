<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Lote\LoteService;
use App\Services\Lote\LoteServiceInterface;
use App\Services\Produto\ProdutoImportService;
use App\Services\Produto\ProdutoImportServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoteServiceInterface::class, LoteService::class);
        $this->app->bind(ProdutoImportServiceInterface::class, ProdutoImportService::class);
    }

    public function boot(): void {}
}
