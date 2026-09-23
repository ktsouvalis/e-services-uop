<?php

use App\Jobs\Pangolin\FetchNewtConnections;
use App\Models\PangolinNewtAgent;
use App\Models\PangolinRun;
use App\Models\User;
use App\Services\Pangolin\NewtAccessLogResolver;
use App\Services\Pangolin\SshCommandRunner;

test('a successful run marks the PangolinRun completed with a synced-count summary', function () {
    PangolinNewtAgent::factory()->create(['ip' => '10.23.2.60']);

    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')->once()->andReturn(
        'ACCESS START session=abc resource=35 proto=tcp src=10.1.2.3:5000 dst=10.23.2.50:22 time=2026-09-22T10:00:00Z'
    );
    $this->app->instance(SshCommandRunner::class, $ssh);

    $resolver = Mockery::mock(NewtAccessLogResolver::class);
    $resolver->shouldReceive('connect')->andThrow(new RuntimeException('unreachable'));
    $resolver->shouldReceive('resolve')->andReturn([
        'user_name' => null, 'user_email' => null, 'client_name' => null, 'site_name' => null, 'resource_name' => null,
    ]);
    $this->app->instance(NewtAccessLogResolver::class, $resolver);

    $run = PangolinRun::factory()->create(['type' => 'newt_connections', 'user_id' => User::factory()->create()->id]);

    (new FetchNewtConnections($run))->handle($this->app->make(\App\Services\Pangolin\NewtConnectionSync::class));

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->summary['synced'])->toBe(1);
    expect($run->summary['agent_errors'])->toBe([]);
    expect($run->started_at)->not->toBeNull();
    expect($run->finished_at)->not->toBeNull();
});

test('a per-agent SSH failure is recorded in the run\'s error field, not thrown', function () {
    PangolinNewtAgent::factory()->create(['name' => 'broken', 'ip' => '10.23.2.61']);

    $ssh = Mockery::mock(SshCommandRunner::class);
    $ssh->shouldReceive('run')->once()->andThrow(new RuntimeException('SSH authentication failed'));
    $this->app->instance(SshCommandRunner::class, $ssh);

    $resolver = Mockery::mock(NewtAccessLogResolver::class);
    $resolver->shouldReceive('connect')->andThrow(new RuntimeException('unreachable'));
    $this->app->instance(NewtAccessLogResolver::class, $resolver);

    $run = PangolinRun::factory()->create(['type' => 'newt_connections', 'user_id' => User::factory()->create()->id]);

    (new FetchNewtConnections($run))->handle($this->app->make(\App\Services\Pangolin\NewtConnectionSync::class));

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->error)->toContain('broken')->toContain('SSH authentication failed');
});
