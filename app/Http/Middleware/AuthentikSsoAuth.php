<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Trusts the identity headers nginx's auth_request forwards from the
// Authentik outpost (see deploy/nginx/site.conf) and maps them onto this
// app's own session/User model. Production only — developing's nginx has
// no auth_request gate and never sends these headers, so LDAP login there
// (AuthenticatedSessionController) is untouched.
class AuthentikSsoAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $username = $request->header('X-Authentik-Username');

        if (! $username) {
            // nginx's auth_request should guarantee this header is present
            // on every request that reaches here — its absence means the
            // outpost gate was bypassed, not that the visitor is a guest.
            abort(401, 'Missing Authentik identity headers.');
        }

        if (Auth::check() && Auth::user()->username === $username) {
            return $next($request);
        }

        $groups = preg_split('/[,|]/', (string) $request->header('X-Authentik-Groups', ''));
        $groups = array_map('trim', $groups);

        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $request->header('X-Authentik-Name', $username),
                'email' => $request->header('X-Authentik-Email'),
                'admin' => in_array(config('authentik.sso.admin_group'), $groups, true),
            ]
        );

        Auth::login($user);

        return $next($request);
    }
}
