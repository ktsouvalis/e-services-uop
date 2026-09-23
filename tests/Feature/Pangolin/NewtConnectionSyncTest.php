<?php

use App\Models\PangolinNewtAgent;
use App\Models\PangolinNewtConnection;
use App\Services\Pangolin\NewtAccessLogParser;
use App\Services\Pangolin\NewtAccessLogResolver;
use App\Services\Pangolin\NewtConnectionSync;
use App\Services\Pangolin\SshCommandRunner;

const NEWT_LOG_ONE_SESSION = 'ACCESS START session=abc resource=35 proto=tcp src=10.1.2.3:5000 dst=10.23.2.50:22 time=2026-09-22T10:00:00Z'."\n".
    'ACCESS END session=abc resource=35 proto=tcp src=10.1.2.3:5000 dst=10.23.2.50:22 started=2026-09-22T10:00:00Z ended=2026-09-22T10:05:00Z duration=5m';

function newtSyncWith(SshCommandRunner $ssh, ?NewtAccessLogResolver $resolver = null): NewtConnectionSync
{
    return new NewtConnectionSync($ssh, new NewtAccessLogParser(), $resolver ?? emptyMapNewtResolver());
}

function emptyMapNewtResolver(): NewtAccessLogResolver
{
    $resolver = Mockery::mock(NewtAccessLogResolver::class);
    $resolver->shouldReceive('connect')->andReturn(Mockery::mock(PDO::class));
    $resolver->shouldReceive('buildLookupMaps')->andReturn([[], [], []]);
    $resolver->shouldReceive('resolve')->andReturnUsing(fn ($session, $siteMap, $clientMap, $targetMap) => [
        'user_name' => null, 'user_email' => null, 'client_name' => null, 'site_name' => null, 'resource_name' => null,
    ]);

    return $resolver;
}

test('a successful sync upserts one row per parsed session', function () {
    $agent = PangolinNewtAgent::factory()->create(['name' => 'patra', 'ip' => '10.23.2.60']);

    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')->once()
        ->with('10.23.2.60', config('pangolin.newt_ssh.username'), config('pangolin.newt_ssh.key_path'), Mockery::pattern('/docker logs --since \d+h newt 2>&1/'))
        ->andReturn(NEWT_LOG_ONE_SESSION);

    $result = newtSyncWith($ssh)->run();

    expect($result['synced'])->toBe(1);
    expect($result['agent_errors'])->toBe([]);
    $this->assertDatabaseCount('pangolin_newt_connections', 1);

    $row = PangolinNewtConnection::first();
    expect($row->newt_agent_id)->toBe($agent->id);
    expect($row->session_id)->toBe('abc');
    expect($row->agent_name)->toBe('patra');
    expect($row->src_ip)->toBe('10.1.2.3');
    expect($row->ended_at)->not->toBeNull();
});

test('running twice against the same log does not duplicate rows, and fills in ended_at for a session that was open before', function () {
    PangolinNewtAgent::factory()->create(['ip' => '10.23.2.60']);

    $openLog = 'ACCESS START session=abc resource=35 proto=tcp src=10.1.2.3:5000 dst=10.23.2.50:22 time=2026-09-22T10:00:00Z';
    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')->once()->andReturn($openLog);
    newtSyncWith($ssh)->run();

    expect(PangolinNewtConnection::first()->ended_at)->toBeNull();

    $ssh2 = Mockery::mock(SshCommandRunner::class);
    $ssh2->shouldReceive('run')->once()->andReturn(NEWT_LOG_ONE_SESSION);
    $result = newtSyncWith($ssh2)->run();

    expect($result['synced'])->toBe(1);
    $this->assertDatabaseCount('pangolin_newt_connections', 1);
    expect(PangolinNewtConnection::first()->ended_at)->not->toBeNull();
});

test('an SSH failure for one agent is recorded as an error and does not block other agents', function () {
    PangolinNewtAgent::factory()->create(['name' => 'broken', 'ip' => '10.23.2.61']);
    PangolinNewtAgent::factory()->create(['name' => 'ok', 'ip' => '10.23.2.62']);

    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')->with('10.23.2.61', Mockery::any(), Mockery::any(), Mockery::any())
        ->andThrow(new RuntimeException('SSH authentication failed'));
    $ssh->shouldReceive('run')->with('10.23.2.62', Mockery::any(), Mockery::any(), Mockery::any())
        ->andReturn(NEWT_LOG_ONE_SESSION);

    $result = newtSyncWith($ssh)->run();

    expect($result['synced'])->toBe(1);
    expect($result['agent_errors'])->toHaveCount(1);
    expect($result['agent_errors'][0])->toContain('broken')->toContain('SSH authentication failed');
});

test('site_name is derived from the connecting agent, not the resolver\'s own per-resource lookup', function () {
    PangolinNewtAgent::factory()->create(['name' => 'patra', 'ip' => '10.23.2.60']);

    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')->once()->andReturn(NEWT_LOG_ONE_SESSION);

    $resolver = Mockery::mock(NewtAccessLogResolver::class);
    $resolver->shouldReceive('connect')->andReturn(Mockery::mock(PDO::class));
    $resolver->shouldReceive('buildLookupMaps')->andReturn([[35 => 'Patras', 36 => 'Tripoli', 69 => 'Kalamata'], [], []]);
    // The resolver's own site_name (via its last-resort resource_id
    // fallback) deliberately disagrees with the agent match, to prove the
    // agent-derived name wins.
    $resolver->shouldReceive('resolve')->andReturn([
        'user_name' => null, 'user_email' => null, 'client_name' => null, 'site_name' => 'Tripoli', 'resource_name' => null,
    ]);

    newtSyncWith($ssh, $resolver)->run();

    expect(PangolinNewtConnection::first()->site_name)->toBe('Patras');
});

test('a Postgres lookup failure falls back to unresolved identity fields rather than failing the whole sync', function () {
    PangolinNewtAgent::factory()->create(['ip' => '10.23.2.60']);

    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')->once()->andReturn(NEWT_LOG_ONE_SESSION);

    $resolver = Mockery::mock(NewtAccessLogResolver::class);
    $resolver->shouldReceive('connect')->andThrow(new RuntimeException('could not connect to server'));
    $resolver->shouldReceive('resolve')->andReturn([
        'user_name' => null, 'user_email' => null, 'client_name' => null, 'site_name' => null, 'resource_name' => null,
    ]);

    $result = newtSyncWith($ssh, $resolver)->run();

    expect($result['synced'])->toBe(1);
    $row = PangolinNewtConnection::first();
    expect($row->user_name)->toBeNull();
    expect($row->src_ip)->toBe('10.1.2.3');
});
