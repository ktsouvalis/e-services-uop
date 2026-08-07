<?php

namespace App\Http\Middleware;

use Closure;

class AdminOnly
{
    public function handle($request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->admin) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
