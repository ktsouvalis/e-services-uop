<?php

use App\Models\PangolinRun;
use App\Models\User;

beforeEach(function () {
    // Real dev/prod .env values leak into the test process for any key
    // phpunit.xml doesn't explicitly force (see CLAUDE.md's Testing section) —
    // pin the cluster topology to nothing so no test here accidentally talks
    // to a real host.
    config([
        'pangolin.nodes' => [],
        'pangolin.newt.hosts' => [],
        'pangolin.vip' => null,
    ]);
});

test('guests are redirected to login rather than reaching any pangolin route unauthenticated', function () {
    $run = PangolinRun::factory()->create(['type' => 'logs']);

    $this->get(route('pangolin.index'))->assertRedirect(route('login'));
    $this->get(route('pangolin.monitor.data'))->assertRedirect(route('login'));
    $this->post(route('pangolin.monitor.refresh'))->assertRedirect(route('login'));
    $this->post(route('pangolin.logs.fetch'))->assertRedirect(route('login'));
    $this->get(route('pangolin.logs.download', $run))->assertRedirect(route('login'));
    $this->post(route('pangolin.resources.import'))->assertRedirect(route('login'));
    $this->post(route('pangolin.resources.normalize'))->assertRedirect(route('login'));
});

test('menu disabled forbids every pangolin route even for an authenticated user', function () {
    disableMenu('pangolin');
    $user = User::factory()->create();
    $run = PangolinRun::factory()->create(['type' => 'logs']);

    $this->actingAs($user)->get(route('pangolin.index'))->assertForbidden();
    $this->actingAs($user)->get(route('pangolin.monitor.data'))->assertForbidden();
    $this->actingAs($user)->post(route('pangolin.monitor.refresh'))->assertForbidden();
    $this->actingAs($user)->post(route('pangolin.logs.fetch'))->assertForbidden();
    $this->actingAs($user)->get(route('pangolin.logs.download', $run))->assertForbidden();
});

test('a non-admin authenticated user can reach pangolin once its menu is enabled', function () {
    // PangolinEnabled currently only checks the menu, not auth()->user()->admin
    // (that check is present in the middleware but commented out) — see
    // CLAUDE.md's Pangolin "Access control" note. This test pins down the
    // real, current behavior so a future admin-gate change is a deliberate
    // edit here, not a silent regression either way.
    enableMenu('pangolin');
    $user = User::factory()->create(['admin' => false]);

    $this->actingAs($user)->get(route('pangolin.index'))->assertOk();
});
