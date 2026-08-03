<?php

namespace App\Services\Authentik;

use Symfony\Component\Yaml\Yaml;

/**
 * Renders config/authentik.php into the config.yml shape authentik-utils'
 * scripts expect (see authentik-utils/config.yml.example).
 */
class ConfigYamlWriter
{
    public function write(string $path): void
    {
        $nodes = config('authentik.nodes', []);

        $config = [
            'site_name' => 'UoP Authentik HA Cluster',
            'unicode_bullets' => true,
            'refresh_interval' => config('authentik.refresh_interval', 20),
            'http_timeout' => config('authentik.http_timeout', 5),
            'vip' => config('authentik.vip'),
            'nodes' => [
                'authentik' => $nodes,
                'patroni' => $nodes,
                'etcd' => $nodes,
                'haproxy' => $nodes,
            ],
            'ports' => [
                'authentik' => config('authentik.ports.authentik'),
                'patroni' => config('authentik.ports.patroni'),
                'etcd' => config('authentik.ports.etcd'),
                'haproxy_stats' => config('authentik.ports.haproxy_stats'),
                'nginx_status' => config('authentik.ports.nginx_status'),
            ],
            'ssh' => [
                'username' => config('authentik.ssh.username'),
                'key_file' => config('authentik.ssh.key_path'),
            ],
            'services' => config('authentik.services', []),
            'credentials' => [
                'haproxy_stats_user' => config('authentik.credentials.haproxy_stats_user'),
                'haproxy_stats_pass' => config('authentik.credentials.haproxy_stats_pass'),
                'authentik_api_token' => config('authentik.credentials.authentik_api_token'),
            ],
            'keepalived' => [
                'track_weight' => config('authentik.keepalived.track_weight', -20),
                'nodes' => $nodes,
            ],
        ];

        if (! is_dir(dirname($path))) {
            // 0777: this tree is written by both the queue-worker container
            // (runs as root) and the web container (runs as www-data) —
            // 0755 lets the non-owner read/traverse but not create new
            // entries, which breaks the other side depending on who creates
            // a given directory first. Only config.yml itself (chmod 0600
            // below) carries credentials and needs to be locked down.
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, Yaml::dump($config, 6, 2));
        chmod($path, 0600);
    }
}
