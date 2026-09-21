<?php

return [

    // SSO (forward-auth outpost in front of this app itself, production only
    // — see App\Http\Middleware\AuthentikSsoAuth). Unrelated to the
    // monitoring/logs tooling below — don't conflate the two.
    'sso' => [
        'admin_group' => env('AUTHENTIK_ADMIN_GROUP', 'dgu-services-admins'),
    ],

    /*
     * akropolis (https://github.com/ktsouvalis/akropolis) is a single-file
     * zipapp release binary — fetched/pinned in the Dockerfile. It owns its
     * config.<site>.monitor.yml schema entirely; this app no longer renders
     * one from env/config (the old ConfigYamlWriter is gone) — the user
     * pastes their own per Logs run, via the Authentik page. See CLAUDE.md.
     * (The Monitor tab no longer uses this binary at all as of 2026-09-21 —
     * see 'monitor' below and App\Services\Authentik\ClusterMonitor.)
     */
    'akropolis' => [
        'bin' => env('AUTHENTIK_AKROPOLIS_BIN', '/opt/akropolis'),
        'process_timeout' => (int) env('AUTHENTIK_PROCESS_TIMEOUT', 600),
    ],

    /*
     * Native HTTP polling for the Monitor tab (App\Services\Authentik\
     * ClusterMonitor) — replaced shelling out to `akropolis monitor` behind
     * a browser terminal, same day it was introduced. The node IP, public
     * Authentik URL, and API token are admin-editable settings persisted in
     * the authentik_monitor_settings table (see AuthentikMonitorSettings),
     * not here — only the fixed, non-sensitive port numbers this specific
     * single-node deployment uses live in config.
     */
    'monitor' => [
        'http_timeout' => (int) env('AUTHENTIK_MONITOR_HTTP_TIMEOUT', 5),
        // No 'authentik_worker' port here — a direct liveness probe on it
        // was tried and dropped, see ClusterMonitor's own docblock.
        'ports' => [
            'authentik' => 443,
            'nginx_status' => 8080,
        ],
    ],

];
