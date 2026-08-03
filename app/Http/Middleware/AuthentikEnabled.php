<?php

namespace App\Http\Middleware;

use App\Models\Menu;
use Closure;
use Illuminate\Support\Facades\Schema;

class AuthentikEnabled
{
    public function handle($request, Closure $next)
    {
        if (Schema::hasTable('menus') && Schema::hasColumn('menus', 'enabled')) {
            $allowed = Menu::where('route_is', 'authentik')->where('enabled', true)->exists();
            if (! $allowed) {
                abort(403, 'Unauthorized.');
            }
        }

        return $next($request);
    }
}
