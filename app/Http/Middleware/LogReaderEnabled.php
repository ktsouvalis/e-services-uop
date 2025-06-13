<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Schema;
use App\Models\Menu;

class LogReaderEnabled
{
    public function handle($request, Closure $next)
    {
        if (Schema::hasTable('menus') && Schema::hasColumn('menus', 'enabled')) {
            $allowed = Menu::where('route_is', 'log-reader')->where('enabled', true)->exists();
            if (!$allowed) {
                abort(403, 'Unauthorized.');
            }
        }

        return $next($request);
    }
}