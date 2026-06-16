<?php

namespace App\Providers;

use App\Services\FichaTecnica\FakeFichaTecnicaService;
use App\Services\FichaTecnica\FichaTecnicaService;
use App\Services\FichaTecnica\FichaTecnicaServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FichaTecnicaServiceInterface::class, function () {
            if (empty(env('DB_HOST'))) {
                return new FakeFichaTecnicaService();
            }

            return new FichaTecnicaService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
