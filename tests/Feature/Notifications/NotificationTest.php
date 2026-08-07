<?php

use App\Models\User;
use App\Notifications\UserNotification;

test('guests are redirected to login for every notification route', function () {
    $user = User::factory()->create();
    $user->notify(new UserNotification('hello', 'summary'));
    $notification = $user->notifications->first();

    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->get(route('notifications.show', $notification->id))->assertRedirect(route('login'));
    $this->post(route('notifications.mark_all_as_read'))->assertRedirect(route('login'));
    $this->post(route('notifications.mark_as_read', $notification->id))->assertRedirect(route('login'));
    $this->post(route('notifications.delete_all', $user))->assertRedirect(route('login'));

    expect($user->fresh()->notifications)->toHaveCount(1);
});

test('a user can view and mark their own notification as read', function () {
    $user = User::factory()->create();
    $user->notify(new UserNotification('hello', 'summary'));
    $notification = $user->notifications->first();

    $this->actingAs($user)->get(route('notifications.show', $notification->id))->assertOk();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot view, mark, or delete another users notification', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $owner->notify(new UserNotification('hello', 'summary'));
    $notification = $owner->notifications->first();

    $this->actingAs($other)->get(route('notifications.show', $notification->id))->assertNotFound();
    $this->actingAs($other)->post(route('notifications.mark_as_read', $notification->id))->assertNotFound();
    $this->actingAs($other)->delete(route('notifications.destroy', $notification->id))->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark all as read only touches the authenticated users own notifications', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->notify(new UserNotification('a', 's'));
    $other->notify(new UserNotification('b', 's'));

    $this->actingAs($user)->post(route('notifications.mark_all_as_read'))->assertRedirect(route('notifications.index'));

    expect($user->fresh()->unreadNotifications)->toHaveCount(0);
    expect($other->fresh()->unreadNotifications)->toHaveCount(1);
});

test('a user cannot delete another users notifications via delete_all', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $owner->notify(new UserNotification('hello', 'summary'));

    $this->actingAs($attacker)->post(route('notifications.delete_all', $owner))->assertForbidden();

    expect($owner->fresh()->notifications)->toHaveCount(1);
});

test('a user can delete all of their own notifications', function () {
    $user = User::factory()->create();
    $user->notify(new UserNotification('a', 's'));
    $user->notify(new UserNotification('b', 's'));

    $this->actingAs($user)->post(route('notifications.delete_all', $user))
        ->assertRedirect(route('notifications.index'));

    expect($user->fresh()->notifications)->toHaveCount(0);
});
