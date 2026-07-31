<?php

namespace App\Http\Middleware;

use App\Models\Menu;
use Closure;
use Illuminate\Support\Facades\Schema;

class PangolinEnabled
{
    public function handle($request, Closure $next)
    {
        // if (! auth()->user()?->admin) {
        //     abort(403, 'Unauthorized.');
        // }

        if (Schema::hasTable('menus') && Schema::hasColumn('menus', 'enabled')) {
            $allowed = Menu::where('route_is', 'pangolin')->where('enabled', true)->exists();
            if (! $allowed) {
                abort(403, 'Unauthorized.');
            }
        }

        return $next($request);
    }
}
