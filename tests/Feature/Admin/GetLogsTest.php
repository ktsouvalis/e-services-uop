<?php

use App\Models\User;

test('health check is public and unauthenticated', function () {
    $this->getJson('/health')->assertOk()->assertJson(['status' => 'OK']);
});

test('guests are redirected to login rather than hitting a fatal error', function () {
    $this->get('/get_logs?date=2026-08-07')->assertRedirect(route('login'));
});

test('non-admin users cannot download logs', function () {
    $user = User::factory()->create(['admin' => false]);

    $response = $this->actingAs($user)->get('/get_logs?date=2026-08-07');

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('admin gets an error when no log files exist for the date', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/get_logs?date=1999-01-01');

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('admin can download a zip of matching log files', function () {
    $admin = User::factory()->admin()->create();
    $date = '2026-08-07';
    file_put_contents(storage_path("logs/laravel-{$date}.log"), 'test log contents');

    $response = $this->actingAs($admin)->get("/get_logs?date={$date}");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('zip');

    @unlink(storage_path("logs/laravel-{$date}.log"));
});
