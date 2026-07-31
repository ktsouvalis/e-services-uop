<?php

namespace App\Services\Pangolin;

use Symfony\Component\Yaml\Yaml;

/**
 * Renders config/pangolin.php into the config.yml shape pangolin-utils'
 * scripts expect (see pangolin-utils/config.yml.example).
 */
class ConfigYamlWriter
{
    public function write(string $path): void
    {
        $nodes = config('pangolin.nodes', []);

        $config = [
            'site_name' => 'UoP Pangolin HA Cluster',
            'pangolin' => [
                'base_url' => config('pangolin.base_url'),
                'org_slug' => config('pangolin.org_slug'),
                'api_key' => config('pangolin.api_key'),
            ],
            'unicode_bullets' => config('pangolin.unicode_bullets', true),
            'refresh_interval' => config('pangolin.refresh_interval', 20),
            'http_timeout' => config('pangolin.http_timeout', 5),
            'vip' => config('pangolin.vip'),
            'nodes' => [
                'pangolin' => $nodes,
                'patroni' => $nodes,
                'etcd' => $nodes,
                'haproxy' => $nodes,
                'newt' => [
                    'ssh' => [
                        'username' => config('pangolin.newt.ssh.username') ?: config('pangolin.ssh.username'),
                        'key_file' => config('pangolin.newt.ssh.key_path') ?: config('pangolin.ssh.key_path'),
                    ],
                    'hosts' => config('pangolin.newt.hosts', []),
                ],
            ],
            'ports' => [
                'pangolin' => config('pangolin.ports.pangolin'),
                'patroni' => config('pangolin.ports.patroni'),
                'haproxy_stats' => config('pangolin.ports.haproxy_stats'),
                'etcd' => config('pangolin.ports.etcd'),
                'postgres' => config('pangolin.ports.postgres'),
            ],
            'ssh' => [
                'username' => config('pangolin.ssh.username'),
                'key_file' => config('pangolin.ssh.key_path'),
            ],
            'services' => config('pangolin.services', []),
            'credentials' => [
                'postgres_user' => config('pangolin.postgres.user'),
                'postgres_password' => config('pangolin.postgres.password'),
                'postgres_db' => config('pangolin.postgres.database'),
            ],
            'keepalived' => [
                'track_weight' => config('pangolin.keepalived.track_weight', -25),
                'nodes' => $nodes,
            ],
        ];

        if (! is_dir(dirname($path))) {
            // 0777: this tree is written by both the queue-worker container
            // (runs as root) and the web container (runs as www-data) —
            // 0755 lets the non-owner read/traverse but not create new
            // entries, which breaks the other side depending on who creates
            // a given directory first. Only config.yml itself (chmod 0600
            // below) carries the API key and needs to be locked down.
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, Yaml::dump($config, 6, 2));
        chmod($path, 0600);
    }
}
