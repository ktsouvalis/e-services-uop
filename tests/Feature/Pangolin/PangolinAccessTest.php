<?php

use App\Models\User;

test('guests are redirected to login rather than reaching any pangolin route unauthenticated', function () {
    $this->get(route('pangolin.index'))->assertRedirect(route('login'));
    $this->post(route('pangolin.resources.import'))->assertRedirect(route('login'));
    $this->post(route('pangolin.resources.normalize'))->assertRedirect(route('login'));
    $this->post(route('pangolin.newt-connections.fetch'))->assertRedirect(route('login'));
});

test('menu disabled forbids every pangolin route even for an authenticated user', function () {
    disableMenu('pangolin');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('pangolin.index'))->assertForbidden();
    $this->actingAs($user)->post(route('pangolin.resources.import'))->assertForbidden();
    $this->actingAs($user)->post(route('pangolin.resources.normalize'))->assertForbidden();
    $this->actingAs($user)->post(route('pangolin.newt-connections.fetch'))->assertForbidden();
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
