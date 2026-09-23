<?php

return [

    // Pangolin Integration API — used by ImportResourceCreator/NormalizeProcessor
    // (App\Services\Pangolin\PangolinApiClient) for the Import/Normalize tabs.
    'base_url' => env('PANGOLIN_API_BASE_URL'),
    'org_slug' => env('PANGOLIN_ORG_SLUG'),
    'api_key' => env('PANGOLIN_API_KEY'),

    'http_timeout' => (int) env('PANGOLIN_HTTP_TIMEOUT', 5),

    // SSH used to pull `docker logs newt` from each admin-managed
    // App\Models\PangolinNewtAgent (App\Services\Pangolin\SshCommandRunner,
    // orchestrated by NewtConnectionSync). One shared username/key for every
    // agent, not per-agent — must be passphrase-less, same reason
    // AUTHENTIK_SSH_KEY_PATH's .env.example comment gives: the queue-worker
    // container has no stdin to answer a passphrase prompt.
    'newt_ssh' => [
        'username' => env('PANGOLIN_NEWT_SSH_USERNAME'),
        'key_path' => env('PANGOLIN_NEWT_SSH_KEY_PATH'),
    ],

    // How far back each fetch looks in Newt's own docker logs. 168h (7
    // days) matches logs_viewer.py's ACCESS_LOG_HOURS default.
    'newt_log_lookback_hours' => (int) env('PANGOLIN_NEWT_LOG_LOOKBACK_HOURS', 168),

    // Direct, read-only Postgres connection to Pangolin's own cluster DB —
    // the only way to resolve which user a Newt session's client IP belongs
    // to (the Integration API has no client-IP-to-user mapping; see
    // App\Services\Pangolin\NewtAccessLogResolver). Reached through the
    // pangolin-tunnel compose service's SSH bastion (a second -L forward on
    // the same connection that already tunnels the Integration API), not a
    // direct connection.
    'postgres' => [
        'host' => env('PANGOLIN_POSTGRES_HOST', 'pangolin-tunnel'),
        'port' => (int) env('PANGOLIN_POSTGRES_PORT', 5432),
        'database' => env('PANGOLIN_POSTGRES_DB'),
        'username' => env('PANGOLIN_POSTGRES_USER'),
        'password' => env('PANGOLIN_POSTGRES_PASSWORD'),
    ],

];
