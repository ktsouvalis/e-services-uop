<?php

return [

    'http_timeout' => (int) env('AUTHENTIK_HTTP_TIMEOUT', 5),
    'refresh_interval' => (int) env('AUTHENTIK_REFRESH_INTERVAL', 20),

    // VIP managed by keepalived across the cluster nodes below
    'vip' => env('AUTHENTIK_VIP'),

    'ssh' => [
        'username' => env('AUTHENTIK_SSH_USERNAME'),
        'key_path' => env('AUTHENTIK_SSH_KEY_PATH', storage_path('app/private/authentik/ssh_key')),
    ],

    'ports' => [
        'authentik' => 9443,
        'patroni' => 8008,
        'etcd' => 2379,
        'haproxy_stats' => 9000,
        'nginx_status' => 8080,
    ],

    'credentials' => [
        'haproxy_stats_user' => env('AUTHENTIK_HAPROXY_STATS_USER', 'admin'),
        'haproxy_stats_pass' => env('AUTHENTIK_HAPROXY_STATS_PASS'),
        'authentik_api_token' => env('AUTHENTIK_API_TOKEN'),
    ],

    /*
     * The 3 Authentik HA nodes — same list reused for the authentik/patroni/
     * etcd/haproxy/keepalived checks (identical shape to authentik-utils'
     * config.yml, which repeats the same 3 entries under every nodes.* key).
     *
     * Sourced from an env JSON blob rather than hardcoded here, same
     * convention as PANGOLIN_NODES_JSON: real cluster IPs are sensitive and
     * only ever live in the git-ignored outer .env, never in this file.
     * Shape: [{"ip":"10.x.x.x","name":"ak-node-1","base_priority":100}, ...]
     */
    'nodes' => json_decode(env('AUTHENTIK_NODES_JSON', '[]'), true),

    'keepalived' => [
        'track_weight' => (int) env('AUTHENTIK_VRRP_TRACK_WEIGHT', -20),
    ],

    // Services polled by logs_viewer.py — label/nodes-group/type/container-or-unit.
    'services' => [
        ['label' => 'Auth Server', 'nodes' => 'authentik', 'type' => 'docker', 'container' => 'authentik-server-1'],
        ['label' => 'Auth Worker', 'nodes' => 'authentik', 'type' => 'docker', 'container' => 'authentik-worker-1'],
        ['label' => 'Patroni', 'nodes' => 'patroni', 'type' => 'systemd', 'unit' => 'patroni'],
        ['label' => 'etcd', 'nodes' => 'etcd', 'type' => 'docker', 'container' => 'etcd'],
        ['label' => 'HAProxy', 'nodes' => 'haproxy', 'type' => 'docker', 'container' => 'haproxy'],
        ['label' => 'Nginx', 'nodes' => 'authentik', 'type' => 'docker', 'container' => 'nginx'],
        ['label' => 'Keepalived', 'nodes' => 'keepalived', 'type' => 'systemd', 'unit' => 'keepalived'],
    ],

    'python' => [
        'bin' => env('AUTHENTIK_PYTHON_BIN', '/opt/authentik-venv/bin/python3'),
        'scripts_path' => env('AUTHENTIK_SCRIPTS_PATH', base_path('authentik-utils')),
        'timeout' => (int) env('AUTHENTIK_PROCESS_TIMEOUT', 600),
    ],

];
