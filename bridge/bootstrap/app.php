<?php

use App\Exceptions\RegistroNaoEncontradoException;
use App\Http\Middleware\VerifyBridgeToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'verify.bridge.token' => VerifyBridgeToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (RegistroNaoEncontradoException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 404);
        });
    })->create();
