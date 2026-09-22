<?php

return [

    // SSO (forward-auth outpost in front of this app itself, production only
    // — see App\Http\Middleware\AuthentikSsoAuth). This is the only
    // Authentik-related config left in the app — the Authentik feature
    // module (Monitor/Logs dashboard at /authentik) was removed entirely,
    // see CLAUDE.md.
    'sso' => [
        'admin_group' => env('AUTHENTIK_ADMIN_GROUP', 'dgu-services-admins'),
    ],

];
