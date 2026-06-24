<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuloAccess
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $user = $request->user();

        if (! $user || ! $user->podeAcessarModulo($modulo)) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso não autorizado a este módulo.',
                'data' => null,
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
