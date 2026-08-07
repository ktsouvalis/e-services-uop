<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    enableMenu('items');
});

test('guests are redirected to login', function () {
    $item = Item::factory()->create();

    $this->get(route('items.index'))->assertRedirect(route('login'));
    $this->get(route('items.edit', $item))->assertRedirect(route('login'));
});

test('access is denied when the items menu is disabled, even for a direct item link', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    disableMenu('items');

    $this->actingAs($user)->get(route('items.index'))->assertForbidden();
    $this->actingAs($user)->get(route('items.edit', $item))->assertForbidden();
});

test('an item without an owner is viewable and editable by any authenticated user', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => null]);

    $this->actingAs($user)->get(route('items.edit', $item))->assertOk();
});

test('an item with an owner can only be edited by that owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get(route('items.edit', $item))->assertOk();
    $this->actingAs($other)->get(route('items.edit', $item))->assertForbidden();
});

test('a user can create an item', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->post(route('items.store'), [
        'category_id' => $category->id,
        'description' => 'Office chair',
    ]);

    $response->assertRedirect(route('items.index'))->assertSessionHas('success');
    $this->assertDatabaseHas('items', [
        'description' => 'Office chair',
        'category_id' => $category->id,
    ]);
});

test('creating an item without a category fails validation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('items.store'), ['description' => 'No category'])
        ->assertSessionHasErrors('category_id');
});

test('marking an item as given away clears its owner and local-storage flag', function () {
    $owner = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $owner->id, 'given_away' => false]);

    $response = $this->actingAs($owner)->post(route('items.given', $item), ['checked' => 'true']);

    $response->assertJson(['status' => 'success']);
    $item->refresh();
    expect($item->given_away)->toBeTrue();
    expect($item->user_id)->toBeNull();
});

test('a non-owner cannot toggle given status on someone elses item', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->post(route('items.given', $item), ['checked' => 'true'])->assertForbidden();
});

test('an admin can delete an item they do not own only if unowned or theirs (policy is ownership-based, not admin-based)', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($admin)->delete(route('items.destroy', $item))->assertForbidden();
});
