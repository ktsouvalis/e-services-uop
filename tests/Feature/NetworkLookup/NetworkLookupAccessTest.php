<?php

use App\Models\NetworkDevice;
use App\Models\User;

test('guests are redirected to login rather than reaching any network-lookup route unauthenticated', function () {
    $device = NetworkDevice::factory()->create();

    $this->get(route('network-lookup.index'))->assertRedirect(route('login'));
    $this->post(route('network-lookup.poll'))->assertRedirect(route('login'));
    $this->post(route('network-lookup.devices.toggle-enabled', $device))->assertRedirect(route('login'));
});

test('menu disabled forbids every network-lookup route even for an authenticated user', function () {
    disableMenu('network-lookup.*');
    $user = User::factory()->create();
    $device = NetworkDevice::factory()->create();

    $this->actingAs($user)->get(route('network-lookup.index'))->assertForbidden();
    $this->actingAs($user)->post(route('network-lookup.poll'))->assertForbidden();
    $this->actingAs($user)->post(route('network-lookup.devices.toggle-enabled', $device))->assertForbidden();
});

test('a non-admin authenticated user can reach network-lookup once its menu is enabled', function () {
    // NetworkLookupEnabled currently only checks the menu, not
    // auth()->user()->admin (that check is present in the middleware but
    // commented out), matching Pangolin/Authentik's current behavior. This
    // test pins down the real, current behavior so a future admin-gate
    // change is a deliberate edit here, not a silent regression either way.
    enableMenu('network-lookup.*');
    $user = User::factory()->create(['admin' => false]);

    $this->actingAs($user)->get(route('network-lookup.index'))->assertOk();
});
