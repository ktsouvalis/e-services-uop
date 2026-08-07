<?php

use App\Models\Menu;
use App\Models\User;

test('guests are redirected to login rather than reaching menu routes unauthenticated', function () {
    $menu = Menu::factory()->create();

    $this->get(route('menus.index'))->assertRedirect(route('login'));
    $this->post(route('menus.store'), ['title' => 'x', 'route' => 'x', 'route_is' => 'x'])->assertRedirect(route('login'));
    $this->get(route('menus.edit', $menu))->assertRedirect(route('login'));
    $this->patch(route('menus.update', $menu), ['title' => 'x'])->assertRedirect(route('login'));
    $this->delete(route('menus.destroy', $menu))->assertRedirect(route('login'));
    $this->post(route('menus.toggle-enabled', $menu), ['enabled' => true])->assertRedirect(route('login'));
});

test('non-admin authenticated users cannot view or manage menus', function () {
    $user = User::factory()->create(['admin' => false]);
    $menu = Menu::factory()->create();

    $this->actingAs($user)->get(route('menus.index'))->assertForbidden();
    $this->actingAs($user)->get(route('menus.edit', $menu))->assertForbidden();
    $this->actingAs($user)->post(route('menus.toggle-enabled', $menu), ['enabled' => true])->assertForbidden();
});

test('admin can list, update and toggle menus', function () {
    $admin = User::factory()->admin()->create();
    $menu = Menu::factory()->create(['enabled' => true]);

    $this->actingAs($admin)->get(route('menus.index'))->assertOk();

    $this->actingAs($admin)->post(route('menus.toggle-enabled', $menu), ['enabled' => false])
        ->assertJson(['success' => true]);
    expect($menu->fresh()->enabled)->toBeFalse();
});
