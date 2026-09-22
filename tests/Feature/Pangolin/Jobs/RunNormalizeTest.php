<?php

use App\Jobs\Pangolin\RunNormalize;
use App\Models\PangolinRun;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'pangolin.nodes' => [],
        'pangolin.vip' => null,
        'pangolin.base_url' => 'https://pangolin.test',
        'pangolin.org_slug' => 'uop',
        'pangolin.api_key' => 'test-key',
    ]);
    // Run directories are named after the PangolinRun id, and sqlite's
    // :memory: RefreshDatabase rolls back per test rather than recreating the
    // schema — ids restart from 1 every test, so leftover files from a
    // previous test's run would otherwise be inherited by this one.
    File::deleteDirectory(storage_path('app/private/pangolin'));
});

/**
 * Wires up the whole Pangolin Integration API surface RunNormalize touches:
 * org/sites/users/site-resources listing, plus per-resource users/roles/
 * clients keyed by siteResourceId, plus catch-all handlers for the mutating
 * calls (update/set-users/create) so tests can assert on them individually
 * via Http::assertSent() without needing to pre-declare every call.
 *
 * @param  array<int, array>  $resources
 * @param  array<int, array<int, array>>  $usersByResourceId
 * @param  array<int, array<int, array>>  $rolesByResourceId
 */
function fakePangolinNormalizeApi(array $resources, array $usersByResourceId = [], array $rolesByResourceId = []): void
{
    Http::fake(function ($request) use ($resources, $usersByResourceId, $rolesByResourceId) {
        $url = $request->url();

        if (str_ends_with($url, '/v1/org/uop')) {
            return Http::response(['data' => ['name' => 'UoP']], 200);
        }
        if (str_contains($url, '/v1/org/uop/sites')) {
            return Http::response(['data' => ['sites' => [['siteId' => 5, 'name' => 'Patra Site']], 'pagination' => ['total' => 1]]], 200);
        }
        if (str_contains($url, '/v1/org/uop/users')) {
            return Http::response(['data' => ['users' => [
                ['id' => 42, 'email' => 'ktsouvalis@uop.gr'],
                ['id' => 43, 'email' => 'jdoe@uop.gr'],
            ], 'pagination' => ['total' => 2]]], 200);
        }
        if (str_contains($url, '/v1/org/uop/site-resources')) {
            return Http::response(['data' => ['siteResources' => $resources, 'pagination' => ['total' => count($resources)]]], 200);
        }
        if (preg_match('#/v1/site-resource/(\d+)/users$#', $url, $m) && $request->method() === 'GET') {
            return Http::response(['data' => ['users' => $usersByResourceId[(int) $m[1]] ?? []]], 200);
        }
        if (preg_match('#/v1/site-resource/(\d+)/roles$#', $url, $m) && $request->method() === 'GET') {
            return Http::response(['data' => ['roles' => $rolesByResourceId[(int) $m[1]] ?? [['roleId' => 1, 'name' => 'Admin']]]], 200);
        }
        if (preg_match('#/v1/site-resource/(\d+)/clients$#', $url)) {
            return Http::response(['data' => ['clients' => []]], 200);
        }
        // Mutating calls: users/1 update, /site-resource/{id}, /site-resource
        // (create), /site-resource/{id}/users (set access).
        if (str_contains($url, '/v1/org/uop/site-resource')) {
            return Http::response(['data' => ['siteResourceId' => 999, 'niceId' => 'split-2302-50-p22']], 200);
        }

        return Http::response([], 200);
    });
}

test('refuses to run unscoped and fails loudly when every requested resource id fails to resolve', function () {
    fakePangolinNormalizeApi([]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => [
        'apply' => true,
        'resource_ids' => ['typo-niceid'],
    ]]);

    RunNormalize::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('failed');
    expect($run->error)->toContain('refusing to run unscoped');
    Http::assertNotSent(fn ($request) => in_array($request->method(), ['PUT', 'POST'], true));
});

test('a dry run scans every resource and makes no mutating calls', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'wrong', 'name' => 'patra-badname-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => true,
            'enabled' => false, 'siteIds' => [5]],
    ], usersByResourceId: [100 => [['userId' => 42, 'email' => 'ktsouvalis@uop.gr']]]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => false, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->summary)->toBe(['DRY-RUN' => 1]);
    Http::assertNotSent(fn ($request) => in_array($request->method(), ['PUT', 'POST'], true));
});

test('an already-correct single-user resource is reported OK with no update calls', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'ktsouvalis-2302-50-p22', 'name' => 'patra-ktsouvalis-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => true,
            'enabled' => true, 'siteIds' => [5]],
    ], usersByResourceId: [100 => [['userId' => 42, 'email' => 'ktsouvalis@uop.gr']]]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    expect($run->fresh()->summary)->toBe(['OK' => 1]);
    Http::assertNotSent(fn ($request) => in_array($request->method(), ['PUT', 'POST'], true));
});

test('applying fixes a mis-named resource: rename, niceId, enable, and icmp all corrected in one update call', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'stale-id', 'name' => 'patra-oldname-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => false,
            'enabled' => false, 'siteIds' => [5]],
    ], usersByResourceId: [100 => [['userId' => 42, 'email' => 'ktsouvalis@uop.gr']]]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    expect($run->fresh()->summary)->toBe(['OK' => 1]);
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/100'
        && $request->method() === 'POST'
        && $request['name'] === 'patra-ktsouvalis-2302-50'
        && $request['niceId'] === 'ktsouvalis-2302-50-p22'
        && $request['enabled'] === true
        && $request['disableIcmp'] === true
        // tcp/udp must always be echoed back unchanged, not omitted —
        // confirmed live API quirk (omitting resets udp to "all").
        && $request['tcpPortRangeString'] === '22'
        && $request['udpPortRangeString'] === '');
});

test('a resource with real UDP ports gets a niceId that incorporates them alongside TCP', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'stale-id', 'name' => 'patra-ktsouvalis-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22,3389', 'udpPortRangeString' => '53', 'disableIcmp' => true,
            'enabled' => true, 'siteIds' => [5]],
    ], usersByResourceId: [100 => [['userId' => 42, 'email' => 'ktsouvalis@uop.gr']]]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    expect($run->fresh()->summary)->toBe(['OK' => 1]);
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/100'
        && $request->method() === 'POST'
        && $request['niceId'] === 'ktsouvalis-2302-50-p22-p3389-u53'
        // UDP ports are audited into the niceId but never opened/blocked by
        // this script — the resource's own real udpPortRangeString is still
        // just echoed back unchanged, same as tcpPortRangeString always was.
        && $request['udpPortRangeString'] === '53');
});

test('a UDP wildcard ("*") skips niceId enforcement entirely, same as a TCP wildcard would', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'whatever', 'name' => 'patra-ktsouvalis-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '*', 'disableIcmp' => true,
            'enabled' => true, 'siteIds' => [5]],
    ], usersByResourceId: [100 => [['userId' => 42, 'email' => 'ktsouvalis@uop.gr']]]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    // Nothing else about this resource needs fixing (name/access/enable/icmp
    // are all already correct) — with niceId enforcement skipped, that means
    // a true no-op, not just "niceId untouched".
    expect($run->fresh()->summary)->toBe(['OK' => 1]);
    Http::assertNotSent(fn ($request) => in_array($request->method(), ['PUT', 'POST'], true));
});

test('a 0-user resource with real UDP ports resolves via the dual-protocol niceId reversal', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'ktsouvalis-2302-50-p22-u53', 'name' => 'patra-something-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '53', 'disableIcmp' => true,
            'enabled' => false, 'siteIds' => [5]],
    ], usersByResourceId: [100 => []]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    expect($run->fresh()->summary)->toBe(['OK' => 1]);
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/100/users'
        && $request->method() === 'POST' && $request['userIds'] === [42]);
});

test('a 0-user resource with no resolvable owner is disabled rather than left reachable via the baseline role', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'legacy', 'name' => 'patra-nomatch-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => true,
            'enabled' => true, 'siteIds' => [5]],
    ], usersByResourceId: [100 => []]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    expect($run->fresh()->summary)->toBe(['OK' => 1]);
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/100'
        && $request->method() === 'POST'
        && $request['enabled'] === false
        && $request['userIds'] === []);
});

test('a 0-user resource whose name exactly matches the naming convention is auto-granted and enabled', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'ktsouvalis-2302-50-p22', 'name' => 'patra-ktsouvalis-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => true,
            'enabled' => false, 'siteIds' => [5]],
    ], usersByResourceId: [100 => []]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    expect($run->fresh()->summary)->toBe(['OK' => 1]);
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/100/users'
        && $request->method() === 'POST' && $request['userIds'] === [42]);
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/100'
        && $request->method() === 'POST' && $request['enabled'] === true);
});

test('a 2+ user resource with no resolvable primary is skipped as ambiguous, access left untouched', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'legacy', 'name' => 'patra-nomatch-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => true,
            'enabled' => true, 'siteIds' => [5]],
    ], usersByResourceId: [100 => [
        ['userId' => 42, 'email' => 'ktsouvalis@uop.gr'],
        ['userId' => 43, 'email' => 'jdoe@uop.gr'],
    ]]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    expect($run->fresh()->summary)->toBe(['SKIPPED' => 1]);
    Http::assertNotSent(fn ($request) => in_array($request->method(), ['PUT', 'POST'], true));
});

test('a 2+ user resource with a resolvable primary splits the other user off into a new resource', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 100, 'niceId' => 'legacy', 'name' => 'patra-ktsouvalis-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => true,
            'enabled' => true, 'siteIds' => [5]],
    ], usersByResourceId: [100 => [
        ['userId' => 42, 'email' => 'ktsouvalis@uop.gr'],
        ['userId' => 43, 'email' => 'jdoe@uop.gr'],
    ]]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    expect($run->fresh()->summary)->toBe(['OK' => 1]);
    // The split creates a new resource for jdoe (PUT, no "enabled" key —
    // schema rejects it on create) then a follow-up POST to enable it.
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/org/uop/site-resource'
        && $request->method() === 'PUT'
        && $request['userIds'] === [43]
        && ! array_key_exists('enabled', $request->data()));
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/999'
        && $request->method() === 'POST' && $request['enabled'] === true);
    // The original is pruned to just the resolved primary.
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/100/users'
        && $request->method() === 'POST' && $request['userIds'] === [42]);
});

test('resolves a niceId to its numeric siteResourceId and scopes the run to just that resource', function () {
    fakePangolinNormalizeApi([
        ['siteResourceId' => 555, 'niceId' => 'mkatsis-2302-50-p22', 'name' => 'patra-mkatsis-2302-50', 'mode' => 'host',
            'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => true,
            'enabled' => true, 'siteIds' => [5]],
        ['siteResourceId' => 556, 'niceId' => 'other-2302-51-p22', 'name' => 'patra-other-2302-51', 'mode' => 'host',
            'destination' => '10.23.2.51', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => true,
            'enabled' => true, 'siteIds' => [5]],
    ]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => [
        'apply' => false,
        'resource_ids' => ['mkatsis-2302-50-p22'],
    ]]);

    RunNormalize::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    // Only siteResourceId 555 was in scope — its resolved single user
    // (there is none configured here) is reported, resource 556 never
    // shows up in the report at all.
    Http::assertNotSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/556/users');
});

test('an unreachable Integration API fails the run with a clear error', function () {
    Http::fake(['https://pangolin.test/*' => Http::response('', 500)]);

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => false, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('failed');
    expect($run->error)->toContain('Pangolin Integration API');
    expect($run->report_path)->toBeNull();
});

test('an update failure mid-apply is reported as FAIL for that resource, not a crashed run', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_ends_with($url, '/v1/org/uop')) {
            return Http::response(['data' => ['name' => 'UoP']], 200);
        }
        if (str_contains($url, '/v1/org/uop/sites')) {
            return Http::response(['data' => ['sites' => [['siteId' => 5, 'name' => 'Patra Site']], 'pagination' => ['total' => 1]]], 200);
        }
        if (str_contains($url, '/v1/org/uop/users')) {
            return Http::response(['data' => ['users' => [['id' => 42, 'email' => 'ktsouvalis@uop.gr']], 'pagination' => ['total' => 1]]], 200);
        }
        if (str_contains($url, '/v1/org/uop/site-resources')) {
            return Http::response(['data' => ['siteResources' => [
                ['siteResourceId' => 100, 'niceId' => 'stale', 'name' => 'patra-oldname-2302-50', 'mode' => 'host',
                    'destination' => '10.23.2.50', 'tcpPortRangeString' => '22', 'udpPortRangeString' => '', 'disableIcmp' => true,
                    'enabled' => true, 'siteIds' => [5]],
            ], 'pagination' => ['total' => 1]]], 200);
        }
        if (str_contains($url, '/users') && $request->method() === 'GET') {
            return Http::response(['data' => ['users' => [['userId' => 42, 'email' => 'ktsouvalis@uop.gr']]]], 200);
        }
        if (str_contains($url, '/roles')) {
            return Http::response(['data' => ['roles' => [['roleId' => 1, 'name' => 'Admin']]]], 200);
        }
        if (str_contains($url, '/clients')) {
            return Http::response(['data' => ['clients' => []]], 200);
        }
        if (str_contains($url, '/v1/site-resource/100') && $request->method() === 'POST') {
            return Http::response(['message' => 'server error'], 500);
        }

        return Http::response([], 200);
    });

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => true, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed'); // the run itself completes and produces a report
    expect($run->summary)->toBe(['FAIL' => 1]);
});
