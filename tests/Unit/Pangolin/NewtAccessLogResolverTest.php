<?php

use App\Services\Pangolin\NewtAccessLogResolver;

beforeEach(function () {
    $this->resolver = new NewtAccessLogResolver();
});

function fakeNewtSession(array $overrides = []): array
{
    return array_merge([
        'session' => 's1', 'resource_id' => 35, 'proto' => 'tcp',
        'src_ip' => '10.1.2.3', 'src_port' => '5000', 'dst_ip' => '10.23.2.50', 'dst_port' => '22',
        'started' => '2026-09-22T10:00:00Z', 'ended' => '2026-09-22T10:05:00Z', 'duration' => '5m',
    ], $overrides);
}

test('resolve() finds a known client ip and known resource name', function () {
    $row = $this->resolver->resolve(
        fakeNewtSession(),
        siteMap: [35 => 'Patra'],
        clientMap: ['10.1.2.3' => ['user_name' => 'Kostas Tsouvalis', 'user_email' => 'ktsouvalis@uop.gr', 'client_name' => 'laptop']],
        targetMap: [35 => 'patra-ktsouvalis-2302-50'],
    );

    expect($row['user_name'])->toBe('Kostas Tsouvalis');
    expect($row['user_email'])->toBe('ktsouvalis@uop.gr');
    expect($row['client_name'])->toBe('laptop');
    // resource_id (35) is also given as a siteMap key here purely to prove
    // site_name's last-resort direct fallback works — see the method's own
    // docblock on why there is no real per-resource site to resolve
    // (resources span every org site for HA), which is why
    // NewtConnectionSync derives site_name from the connecting agent
    // instead in practice.
    expect($row['resource_name'])->toBe('patra-ktsouvalis-2302-50');
    expect($row['site_name'])->toBe('Patra');
});

test('resolve() leaves identity fields null when the client ip is unknown', function () {
    $row = $this->resolver->resolve(fakeNewtSession(), siteMap: [], clientMap: [], targetMap: []);

    expect($row['user_name'])->toBeNull();
    expect($row['user_email'])->toBeNull();
    expect($row['client_name'])->toBeNull();
});

test('resolve() leaves site_name null when the site is unknown, given no target match either', function () {
    $row = $this->resolver->resolve(fakeNewtSession(['resource_id' => 99]), siteMap: [], clientMap: [], targetMap: []);

    expect($row['site_name'])->toBeNull();
    expect($row['resource_name'])->toBeNull();
});

test('resolve() finds the known site name when there is no target match', function () {
    $row = $this->resolver->resolve(fakeNewtSession(), siteMap: [35 => 'Patra'], clientMap: [], targetMap: []);

    expect($row['site_name'])->toBe('Patra');
    expect($row['resource_name'])->toBeNull();
});

test('resolve() falls back to a null user_name when a client is known but has no linked user', function () {
    $row = $this->resolver->resolve(
        fakeNewtSession(),
        siteMap: [],
        clientMap: ['10.1.2.3' => ['user_name' => null, 'user_email' => null, 'client_name' => 'laptop']],
        targetMap: [],
    );

    expect($row['user_name'])->toBeNull();
    expect($row['client_name'])->toBe('laptop');
});
