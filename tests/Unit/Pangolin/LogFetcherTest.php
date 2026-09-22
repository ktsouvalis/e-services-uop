<?php

use App\Services\Pangolin\LogFetcher;
use App\Services\Pangolin\SshCommandRunner;

test('a docker service builds a grep-filtered, tail-limited docker logs command', function () {
    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')
        ->once()
        ->with('10.1.1.1', 'user', '/key', "docker logs --since 24h pangolin 2>&1 | grep -iE '(WARN|WARNING|ERROR|CRITICAL|FATAL|CRIT)' | tail -500")
        ->andReturn('some output');

    $nodeMap = ['node-1' => ['ip' => '10.1.1.1', 'ssh' => ['user', '/key'], 'services' => [['Pangolin', 'docker', 'pangolin']]]];
    $results = (new LogFetcher($ssh))->fetchAll($nodeMap, 24, 'warning');

    expect($results[LogFetcher::key('node-1', 'Pangolin')])->toBe(['output' => 'some output', 'error' => null]);
});

test('a systemd service builds a journalctl command with the level mapped to its priority', function () {
    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')
        ->once()
        ->with('10.1.1.1', 'user', '/key', "journalctl -u patroni --since '48 hours ago' --no-pager -p err -o short-iso | tail -500")
        ->andReturn('');

    $nodeMap = ['node-1' => ['ip' => '10.1.1.1', 'ssh' => ['user', '/key'], 'services' => [['Patroni', 'systemd', 'patroni']]]];
    (new LogFetcher($ssh))->fetchAll($nodeMap, 48, 'error');
});

test('the debug level has no grep filter at all', function () {
    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')
        ->once()
        ->with('10.1.1.1', 'user', '/key', 'docker logs --since 24h pangolin 2>&1 | tail -500')
        ->andReturn('');

    $nodeMap = ['node-1' => ['ip' => '10.1.1.1', 'ssh' => ['user', '/key'], 'services' => [['Pangolin', 'docker', 'pangolin']]]];
    (new LogFetcher($ssh))->fetchAll($nodeMap, 24, 'debug');
});

test('an SSH failure for one service is recorded as an error, not thrown', function () {
    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')->once()->andThrow(new RuntimeException('SSH authentication failed'));

    $nodeMap = ['node-1' => ['ip' => '10.1.1.1', 'ssh' => ['user', '/key'], 'services' => [['Pangolin', 'docker', 'pangolin']]]];
    $results = (new LogFetcher($ssh))->fetchAll($nodeMap, 24, 'warning');

    expect($results[LogFetcher::key('node-1', 'Pangolin')])->toBe(['output' => '', 'error' => 'SSH authentication failed']);
});

test('fetchNewtFullLogs fetches the full ungrepped newt log over the access-log window', function () {
    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')
        ->once()
        ->with('10.23.2.60', 'root', '/keys/newt', 'docker logs --since 168h newt 2>&1')
        ->andReturn('raw newt log');

    $newtNodeMap = ['patra' => ['ip' => '10.23.2.60', 'ssh' => ['root', '/keys/newt']]];
    $results = (new LogFetcher($ssh))->fetchNewtFullLogs($newtNodeMap, 168);

    expect($results['patra'])->toBe(['raw' => 'raw newt log', 'error' => null]);
});
