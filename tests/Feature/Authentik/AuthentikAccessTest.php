<?php

use App\Models\AuthentikLogRun;
use App\Models\User;

beforeEach(function () {
    config([
        'authentik.nodes' => [],
        'authentik.vip' => null,
        'authentik.credentials.authentik_api_token' => null,
    ]);
});

test('guests are redirected to login rather than reaching any authentik route unauthenticated', function () {
    $run = AuthentikLogRun::factory()->create();

    $this->get(route('authentik.index'))->assertRedirect(route('login'));
    $this->get(route('authentik.monitor.data'))->assertRedirect(route('login'));
    $this->post(route('authentik.monitor.refresh'))->assertRedirect(route('login'));
    $this->post(route('authentik.logs.fetch'))->assertRedirect(route('login'));
    $this->get(route('authentik.logs.download', $run))->assertRedirect(route('login'));
});

test('menu disabled forbids every authentik route even for an authenticated user', function () {
    disableMenu('authentik');
    $user = User::factory()->create();
    $run = AuthentikLogRun::factory()->create();

    $this->actingAs($user)->get(route('authentik.index'))->assertForbidden();
    $this->actingAs($user)->get(route('authentik.monitor.data'))->assertForbidden();
    $this->actingAs($user)->post(route('authentik.monitor.refresh'))->assertForbidden();
    $this->actingAs($user)->post(route('authentik.logs.fetch'))->assertForbidden();
    $this->actingAs($user)->get(route('authentik.logs.download', $run))->assertForbidden();
});

test('a non-admin authenticated user can reach authentik once its menu is enabled', function () {
    // AuthentikEnabled deliberately mirrors Pangolin's current (menu-only)
    // behavior, not an admin-only gate — see CLAUDE.md's Authentik "Access
    // control" note.
    enableMenu('authentik');
    $user = User::factory()->create(['admin' => false]);

    $this->actingAs($user)->get(route('authentik.index'))->assertOk();
});
