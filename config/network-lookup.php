<?php

return [

    // Single shared SSH credential used against every device (both vendors) -
    // confirmed from the real configs that Huawei devices authenticate via
    // local-user password (no key assigned) and Cisco devices via a plain VTY
    // password, so this is username+password auth, not key-based like
    // Pangolin/Authentik's ClusterMonitor.
    'ssh' => [
        'username' => env('NETWORK_LOOKUP_SSH_USERNAME'),
        'password' => env('NETWORK_LOOKUP_SSH_PASSWORD'),
        'connect_timeout' => (int) env('NETWORK_LOOKUP_SSH_CONNECT_TIMEOUT', 10),
        'exec_timeout' => (int) env('NETWORK_LOOKUP_SSH_EXEC_TIMEOUT', 20),
    ],

    // MAC/ARP tables don't need per-minute freshness like Pangolin/Authentik's
    // cluster health checks - default to every 10 minutes.
    'poll_interval_minutes' => (int) env('NETWORK_LOOKUP_POLL_INTERVAL', 10),

    // The one device (role = 'core' in network_devices) that display arp / ARP
    // data is fetched from - identified by name, not hardcoded IP, since the
    // device inventory itself lives in the network_devices table.
    'core_device_name' => env('NETWORK_LOOKUP_CORE_DEVICE_NAME', 'KEDD_Central_S6730'),

    // Hand-maintained device list (name/ip/vendor/role), read by
    // `network-lookup:sync-devices` - a git-ignored file rather than a
    // PANGOLIN_NODES_JSON-style env var, since a ~49-entry JSON blob is
    // painful to hand-edit as a single .env line. Same "real topology never
    // committed" rationale as Pangolin/Authentik's node lists though.
    'devices_file' => env('NETWORK_LOOKUP_DEVICES_FILE', storage_path('app/private/network-lookup/devices.json')),

];
