<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\ApontamentoRepository;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use App\Repositories\Contracts\FichaApontamentoRepositoryInterface;
use App\Repositories\Contracts\HistoricoLoteRepositoryInterface;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\FichaApontamentoRepository;
use App\Repositories\HistoricoLoteRepository;
use App\Repositories\SessaoTrabalhoRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(SessaoTrabalhoRepositoryInterface::class, SessaoTrabalhoRepository::class);
        $this->app->bind(ApontamentoRepositoryInterface::class, ApontamentoRepository::class);
        $this->app->bind(FichaApontamentoRepositoryInterface::class, FichaApontamentoRepository::class);
        $this->app->bind(HistoricoLoteRepositoryInterface::class, HistoricoLoteRepository::class);
    }
}
