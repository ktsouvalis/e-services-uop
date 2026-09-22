<?php

use App\Models\PangolinNewtAgent;
use App\Services\Pangolin\LogsNodeMap;

beforeEach(function () {
    config([
        'pangolin.nodes' => [
            ['ip' => '10.20.30.1', 'name' => 'node-1'],
            ['ip' => '10.20.30.2', 'name' => 'node-2'],
        ],
        'pangolin.ssh' => ['username' => 'cluster-user', 'key_path' => '/keys/cluster'],
        'pangolin.newt' => ['ssh' => ['username' => null, 'key_path' => null]],
        'pangolin.services' => [
            ['label' => 'Pangolin', 'nodes' => 'pangolin', 'type' => 'docker', 'container' => 'pangolin'],
            ['label' => 'Patroni', 'nodes' => 'patroni', 'type' => 'systemd', 'unit' => 'patroni'],
            ['label' => 'Newt', 'nodes' => 'newt', 'type' => 'docker', 'container' => 'newt'],
        ],
    ]);
});

test('every real cluster node gets one entry per service mapped to its node group', function () {
    $map = (new LogsNodeMap())->build();

    expect(array_keys($map))->toBe(['node-1', 'node-2']);
    expect($map['node-1']['ip'])->toBe('10.20.30.1');
    expect($map['node-1']['services'])->toBe([
        ['Pangolin', 'docker', 'pangolin'],
        ['Patroni', 'systemd', 'patroni'],
    ]);
});

test('cluster nodes use the global ssh credentials', function () {
    $map = (new LogsNodeMap())->build();

    expect($map['node-1']['ssh'])->toBe(['cluster-user', '/keys/cluster']);
});

test('newt agents come from the database, not config, with their own service entry', function () {
    PangolinNewtAgent::create(['name' => 'patra', 'ip' => '10.23.2.60']);
    PangolinNewtAgent::create(['name' => 'kalamata', 'ip' => '10.23.3.60']);

    $map = (new LogsNodeMap())->build();

    expect(array_keys($map))->toBe(['node-1', 'node-2', 'patra', 'kalamata']);
    expect($map['patra']['ip'])->toBe('10.23.2.60');
    expect($map['patra']['services'])->toBe([['Newt', 'docker', 'newt']]);
});

test('newt-specific ssh credentials override the global ones when configured', function () {
    config(['pangolin.newt.ssh' => ['username' => 'root', 'key_path' => '/keys/newt']]);
    PangolinNewtAgent::create(['name' => 'patra', 'ip' => '10.23.2.60']);

    $map = (new LogsNodeMap())->build();

    expect($map['patra']['ssh'])->toBe(['root', '/keys/newt']);
});

test('newt falls back to the global ssh credentials when no newt-specific override is configured', function () {
    PangolinNewtAgent::create(['name' => 'patra', 'ip' => '10.23.2.60']);

    $map = (new LogsNodeMap())->build();

    expect($map['patra']['ssh'])->toBe(['cluster-user', '/keys/cluster']);
});

test('with no newt agents configured, no newt node appears in the map at all', function () {
    $map = (new LogsNodeMap())->build();

    expect($map)->not->toHaveKey('patra');
    expect(collect($map)->flatMap(fn ($n) => $n['services'])->pluck(0))->not->toContain('Newt');
});
