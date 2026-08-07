<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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

test('the show route is not registered', function () {
    $owner = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $owner->id]);

    // /items/{item} has the same single-segment shape as update/destroy's PUT/PATCH/DELETE,
    // so GET now correctly 405s (method not registered for that URI) rather than reaching
    // a non-existent ItemController::show() and fataling with a 500.
    $this->actingAs($owner)->get('/items/' . $item->id)->assertStatus(405);
});

test('uploading a non-pdf file is rejected by validation', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('items.store'), [
        'category_id' => $category->id,
        'description' => 'Desk',
        'file_path' => [UploadedFile::fake()->create('list.txt', 10, 'text/plain')],
    ])->assertSessionHasErrors('file_path.0');
});

test('a user can upload a file, download it, then delete it', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

    $this->actingAs($user)->post(route('items.store'), [
        'category_id' => $category->id,
        'description' => 'Laptop',
        'file_path' => [$file],
    ])->assertRedirect(route('items.index'));

    $item = Item::first();
    expect($item->files)->toHaveCount(1);
    $stored = $item->files[0]['stored'];

    // Not using Storage::fake(): the controller reads/writes files via a raw
    // storage_path() call rather than the Storage facade, so a faked disk would
    // make these checks look at the wrong directory.
    expect(file_exists(storage_path('app/private/items/' . $stored)))->toBeTrue();

    $this->actingAs($user)
        ->get(route('items.download_file', ['item' => $item, 'filename' => $stored]))
        ->assertOk();

    $this->actingAs($user)
        ->delete(route('items.delete_file', $item), ['filename' => $stored])
        ->assertRedirect();

    expect(file_exists(storage_path('app/private/items/' . $stored)))->toBeFalse();
    expect($item->fresh()->files)->toHaveCount(0);
});

test('destroying an item deletes its uploaded files from storage', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

    $this->actingAs($user)->post(route('items.store'), [
        'category_id' => $category->id,
        'description' => 'Chair',
        'file_path' => [$file],
    ]);

    $item = Item::first();
    $stored = $item->files[0]['stored'];
    expect(file_exists(storage_path('app/private/items/' . $stored)))->toBeTrue();

    $this->actingAs($user)->delete(route('items.destroy', $item))->assertJson(['status' => 'success']);

    expect(file_exists(storage_path('app/private/items/' . $stored)))->toBeFalse();
});

test('extract downloads an xlsx of items not given away', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    Item::factory()->create(['category_id' => $category->id, 'given_away' => false]);

    $response = $this->actingAs($user)->get(route('items.extract'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    // deleteFileAfterSend()'s terminating callback doesn't run under the test HTTP
    // kernel, so the generated xlsx is left behind - clean it up rather than leaving
    // artifacts in storage/app/private/items/exports/.
    @unlink(storage_path('app/private/items/exports/items_' . now()->format('Y-m-d') . '.xlsx'));
});

test('toggling in_local_storage on updates the flag and clears given_away', function () {
    $owner = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $owner->id, 'given_away' => true, 'in_local_storage' => false]);

    $response = $this->actingAs($owner)->post(route('items.in-local-storage', $item), ['checked' => 'true']);

    $response->assertJson(['status' => 'success']);
    $item->refresh();
    expect($item->in_local_storage)->toBeTrue();
    expect($item->given_away)->toBeFalse();
});

test('a non-owner cannot toggle in_local_storage on someone elses item', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->post(route('items.in-local-storage', $item), ['checked' => 'true'])->assertForbidden();
});
