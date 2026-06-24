<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRotinaAccess
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $user = $request->user();

        if (! $user || ! $user->podeAcessarRotina($slug)) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso não autorizado a esta rotina.',
                'data' => null,
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
