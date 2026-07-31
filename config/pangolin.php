<?php

return [

    // Pangolin Integration API — used by create_private_resources.py / normalize_private_resources.py
    'base_url' => env('PANGOLIN_API_BASE_URL'),
    'org_slug' => env('PANGOLIN_ORG_SLUG'),
    'api_key' => env('PANGOLIN_API_KEY'),

    'http_timeout' => (int) env('PANGOLIN_HTTP_TIMEOUT', 5),
    'refresh_interval' => (int) env('PANGOLIN_REFRESH_INTERVAL', 20),
    'unicode_bullets' => true,

    // VIP managed by keepalived across the cluster nodes below
    'vip' => env('PANGOLIN_VIP'),

    'ssh' => [
        'username' => env('PANGOLIN_SSH_USERNAME'),
        'key_path' => env('PANGOLIN_SSH_KEY_PATH', storage_path('app/private/pangolin/ssh_key')),
    ],

    'ports' => [
        'pangolin' => 3001,
        'patroni' => 8008,
        'haproxy_stats' => 9000,
        'etcd' => 2379,
        'postgres' => 5432,
    ],

    // Used by logs_viewer.py to resolve Newt access-log sessions (site/client/
    // resource IDs) into human-readable names — without this it silently
    // falls back to raw IPs/IDs (get_pg_connection() in that script).
    'postgres' => [
        'user' => env('PANGOLIN_POSTGRES_USER', 'postgres'),
        'password' => env('PANGOLIN_POSTGRES_PASSWORD'),
        'database' => env('PANGOLIN_POSTGRES_DB', 'pangolin'),
    ],

    /*
     * The 3 Pangolin HA nodes. Reused for the pangolin/patroni/etcd/haproxy
     * checks and for keepalived priority calculation — same shape as
     * pangolin-utils/config.yml.example, which lists identical entries per
     * group in this cluster's actual topology.
     *
     * Sourced from an env JSON blob rather than hardcoded here: pangolin-utils
     * itself treats real node IPs as sensitive (its own config.yml, unlike
     * config.yml.example, is git-ignored) — this app follows the same
     * convention, so real topology only ever lives in the git-ignored .env.
     * Shape: [{"ip":"10.x.x.x","name":"pangolin-node-1","base_priority":100}, ...]
     */
    'nodes' => json_decode(env('PANGOLIN_NODES_JSON', '[]'), true),

    'keepalived' => [
        'track_weight' => (int) env('PANGOLIN_VRRP_TRACK_WEIGHT', -25),
    ],

    // Newt agent hosts — reachability checked via SSH only, no container inspection.
    'newt' => [
        'ssh' => [
            'username' => env('PANGOLIN_NEWT_SSH_USERNAME'),
            'key_path' => env('PANGOLIN_NEWT_SSH_KEY_PATH'),
        ],
        // Shape: [{"ip":"10.x.x.x","name":"patra"}, ...]
        'hosts' => json_decode(env('PANGOLIN_NEWT_HOSTS_JSON', '[]'), true),
    ],

    // Services polled by logs_viewer.py — label/nodes-group/type/container-or-unit.
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

    'python' => [
        'bin' => env('PANGOLIN_PYTHON_BIN', '/opt/pangolin-venv/bin/python3'),
        'scripts_path' => env('PANGOLIN_SCRIPTS_PATH', base_path('pangolin-utils')),
        'timeout' => (int) env('PANGOLIN_PROCESS_TIMEOUT', 600),
    ],

];
