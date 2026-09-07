<?php

use App\Models\AuthentikLogRun;
use App\Models\PangolinRun;
use App\Models\User;
use Illuminate\Support\Facades\File;

test('guests are redirected to login for every users route', function () {
    $user = User::factory()->create();

    $this->get(route('users.index'))->assertRedirect(route('login'));
    $this->get(route('users.edit', $user))->assertRedirect(route('login'));
    $this->post(route('users.store'))->assertRedirect(route('login'));
    $this->patch(route('users.update', $user))->assertRedirect(route('login'));
    $this->delete(route('users.destroy', $user))->assertRedirect(route('login'));
});

test('non-admin users are forbidden from every users route', function () {
    $user = User::factory()->create(['admin' => false]);
    $other = User::factory()->create();

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    $this->actingAs($user)->get(route('users.edit', $other))->assertForbidden();
    $this->actingAs($user)->post(route('users.store'), [
        'name' => 'New Person', 'username' => 'newperson',
    ])->assertForbidden();
    $this->actingAs($user)->patch(route('users.update', $other), [
        'name' => 'Renamed',
    ])->assertForbidden();
    $this->actingAs($user)->delete(route('users.destroy', $other))->assertForbidden();

    expect(User::where('username', 'newperson')->exists())->toBeFalse();
});

test('admin can view the users index', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertViewIs('users.index')
        ->assertViewHas('users');
});

test('admin can create a user', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Jane Doe',
        'username' => 'jdoe',
    ]);

    $response->assertRedirect(route('users.index'))->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'username' => 'jdoe',
        'name' => 'Jane Doe',
        'email' => 'jdoe@uop.gr',
        'admin' => false,
    ]);
});

test('admin can create another admin user via the admin flag', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Root Person',
        'username' => 'rootperson',
        'admin' => '1',
    ]);

    $this->assertDatabaseHas('users', [
        'username' => 'rootperson',
        'admin' => true,
    ]);
});

test('creating a user without required fields fails validation', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [])
        ->assertSessionHasErrors(['name', 'username']);
});

test('admin can update a user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($admin)->patch(route('users.update', $target), [
        'name' => 'New Name',
        'username' => $target->username,
    ]);

    $response->assertRedirect(route('users.index'))->assertSessionHas('success');
    expect($target->fresh()->name)->toBe('New Name');
});

test('admin can delete a user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));

    $this->assertModelMissing($target);
});

test('deleting a user cleans up their pangolin and authentik run directories on disk, not just the DB rows', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $pangolinRun = PangolinRun::factory()->create(['type' => 'logs', 'user_id' => $target->id]);
    $pangolinDir = storage_path("app/private/pangolin/runs/{$pangolinRun->id}");
    File::ensureDirectoryExists($pangolinDir);
    File::put("{$pangolinDir}/cluster_logs.log", 'log contents');

    $authentikRun = AuthentikLogRun::factory()->create(['user_id' => $target->id]);
    $authentikDir = storage_path("app/private/authentik/runs/{$authentikRun->id}");
    File::ensureDirectoryExists($authentikDir);
    File::put("{$authentikDir}/cluster_logs.log", 'log contents');

    $this->actingAs($admin)->delete(route('users.destroy', $target));

    $this->assertModelMissing($target);
    // pangolin_runs/authentik_log_runs both cascadeOnDelete() on user_id, so the
    // DB rows go away for free — the point of this test is the on-disk directories,
    // which nothing else ever cleans up.
    expect(File::isDirectory($pangolinDir))->toBeFalse();
    expect(File::isDirectory($authentikDir))->toBeFalse();
});
