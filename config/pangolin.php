<?php

return [

    // Pangolin Integration API — used by ImportResourceCreator/NormalizeProcessor
    // (App\Services\Pangolin\PangolinApiClient) for the Import/Normalize tabs.
    'base_url' => env('PANGOLIN_API_BASE_URL'),
    'org_slug' => env('PANGOLIN_ORG_SLUG'),
    'api_key' => env('PANGOLIN_API_KEY'),

    'http_timeout' => (int) env('PANGOLIN_HTTP_TIMEOUT', 5),

    // VIP managed by keepalived across the cluster nodes below. Not read by
    // the Monitor tab (single-node/DB-configurable — see App\Services\
    // Pangolin\ClusterMonitor + pangolin_monitor_settings); kept for
    // possible future HA-aware tooling, currently unused.
    'vip' => env('PANGOLIN_VIP'),

    // Cluster-node SSH credentials — used by the Logs tab's LogFetcher for
    // docker/journalctl log fetching (App\Services\Pangolin\LogsNodeMap).
    'ssh' => [
        'username' => env('PANGOLIN_SSH_USERNAME'),
        'key_path' => env('PANGOLIN_SSH_KEY_PATH', storage_path('app/private/pangolin/ssh_key')),
    ],

    'ports' => [
        // Monitor tab's direct pangolin API health check.
        'pangolin' => 3001,
        // NewtAccessLogResolver's direct Postgres connection for the Logs
        // tab's Newt access-log resolution.
        'postgres' => 5432,
    ],

    // NewtAccessLogResolver's direct Postgres connection (see 'ports.postgres'
    // above) — without this it silently falls back to raw IPs/IDs in the
    // Newt access-log CSV.
    'postgres' => [
        'user' => env('PANGOLIN_POSTGRES_USER', 'postgres'),
        'password' => env('PANGOLIN_POSTGRES_PASSWORD'),
        'database' => env('PANGOLIN_POSTGRES_DB', 'pangolin'),
    ],

    /*
     * The 3 Pangolin HA nodes — read by LogsNodeMap for the Logs tab, which
     * genuinely fetches from every physical node in the real cluster (the
     * Monitor tab no longer reads this at all, see App\Services\Pangolin\
     * ClusterMonitor's docblock).
     *
     * Sourced from an env JSON blob rather than hardcoded here: real node
     * IPs are only ever kept in the git-ignored outer .env, never committed
     * here.
     * Shape: [{"ip":"10.x.x.x","name":"pangolin-node-1"}, ...]
     */
    'nodes' => json_decode(env('PANGOLIN_NODES_JSON', '[]'), true),

    // Newt agent SSH credentials — reachability checked via SSH only, no
    // container inspection. The agents themselves (which hosts exist) are
    // admin-managed in the pangolin_newt_agents table, not a fixed env
    // list — the count isn't stable, so it needs to be addable/removable at
    // runtime. See App\Models\PangolinNewtAgent.
    'newt' => [
        'ssh' => [
            'username' => env('PANGOLIN_NEWT_SSH_USERNAME'),
            'key_path' => env('PANGOLIN_NEWT_SSH_KEY_PATH'),
        ],
    ],

    // Services polled by the Logs tab (App\Services\Pangolin\LogsNodeMap/
    // LogFetcher) — label/nodes-group/type/container-or-unit.
    'services' => [
        ['label' => 'Pangolin', 'nodes' => 'pangolin', 'type' => 'docker', 'container' => 'pangolin'],
        ['label' => 'Gerbil', 'nodes' => 'pangolin', 'type' => 'docker', 'container' => 'gerbil'],
        ['label' => 'Traefik', 'nodes' => 'pangolin', 'type' => 'docker', 'container' => 'traefik'],
        ['label' => 'Patroni', 'nodes' => 'patroni', 'type' => 'systemd', 'unit' => 'patroni'],
        ['label' => 'etcd', 'nodes' => 'etcd', 'type' => 'docker', 'container' => 'etcd'],
        ['label' => 'HAProxy', 'nodes' => 'haproxy', 'type' => 'docker', 'container' => 'haproxy'],
        ['label' => 'Keepalived', 'nodes' => 'keepalived', 'type' => 'systemd', 'unit' => 'keepalived'],
        ['label' => 'Newt', 'nodes' => 'newt', 'type' => 'docker', 'container' => 'newt'],
    ],

];
