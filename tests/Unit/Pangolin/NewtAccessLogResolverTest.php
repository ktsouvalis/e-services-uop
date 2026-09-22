<?php

use App\Services\Pangolin\NewtAccessLogResolver;

beforeEach(function () {
    $this->resolver = new NewtAccessLogResolver();
});

function fakeSession(array $overrides = []): array
{
    return array_merge([
        'session' => 's1', 'resource_id' => 35, 'proto' => 'tcp',
        'src_ip' => '10.1.2.3', 'src_port' => '5000', 'dst_ip' => '10.23.2.50', 'dst_port' => '22',
        'started' => '2026-09-22T10:00:00Z', 'ended' => '2026-09-22T10:05:00Z', 'duration' => '5m',
    ], $overrides);
}

test('formatSession resolves a known client ip and known resource name', function () {
    $row = $this->resolver->formatSession(
        fakeSession(),
        siteMap: [35 => 'Patra'],
        clientMap: ['10.1.2.3' => 'ktsouvalis (laptop)'],
        targetMap: [35 => 'patra-ktsouvalis-2302-50'],
    );

    expect($row['who'])->toBe('ktsouvalis (laptop)');
    // resource_id (35) happens to coincide with a targetMap key here only
    // because the test set it up that way — see the method's own docblock
    // about resource_id actually being a siteId, not a siteResourceId, so
    // this lookup practically never hits in real data.
    expect($row['where'])->toBe('patra-ktsouvalis-2302-50');
    expect($row['proto'])->toBe('TCP');
    expect($row['dst'])->toBe('10.23.2.50:22');
});

test('formatSession falls back to the raw src ip when the client is unknown', function () {
    $row = $this->resolver->formatSession(fakeSession(), siteMap: [], clientMap: [], targetMap: []);

    expect($row['who'])->toBe('10.1.2.3');
});

test('formatSession falls back to "site#N" when the site is unknown, given no target match either', function () {
    $row = $this->resolver->formatSession(fakeSession(['resource_id' => 99]), siteMap: [], clientMap: [], targetMap: []);

    expect($row['where'])->toBe('site#99');
});

test('formatSession falls back to the known site name when there is no target match', function () {
    $row = $this->resolver->formatSession(fakeSession(), siteMap: [35 => 'Patra'], clientMap: [], targetMap: []);

    expect($row['where'])->toBe('Patra');
});

test('formatSession reports "ongoing" and an em-dash for a still-open session', function () {
    $row = $this->resolver->formatSession(fakeSession(['ended' => null, 'duration' => null]), [], [], []);

    expect($row['ended'])->toBe('ongoing');
    expect($row['duration'])->toBe('—');
});
