<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBridgeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('bridge.token');
        $received = (string) $request->header('X-Bridge-Token', '');

        if ($expected === '' || ! hash_equals($expected, $received)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
