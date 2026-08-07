<?php

use App\Models\User;

test('guests are redirected to login', function () {
    $this->get('/jobs')->assertRedirect(route('login'));
});

test('non-admin authenticated users cannot view the jobs monitor', function () {
    $user = User::factory()->create(['admin' => false]);

    $this->actingAs($user)->get('/jobs')->assertForbidden();
});

test('admin can view the jobs monitor', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/jobs')->assertOk();
});
