<?php

use App\Services\Authentik\RememberedConfig;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(storage_path('app/private/authentik'));
});

// afterEach, not just beforeEach — storage_path() is the real, shared dev
// storage tree (not swapped out for tests) and `make test` runs as root, so
// a file this suite creates and leaves behind (like the one below) is
// root-owned and breaks the *real* app's later www-data requests to the
// same fixed path. See AuthentikControllerTest.php's identical note — this
// is the exact test that caused that live incident.
afterEach(function () {
    File::deleteDirectory(storage_path('app/private/authentik'));
});

test('get returns null when nothing has ever been remembered', function () {
    expect(app(RememberedConfig::class)->get())->toBeNull();
});

test('remember persists the value and get returns it back', function () {
    $remembered = app(RememberedConfig::class);

    $remembered->remember("site_name: first\n");
    expect($remembered->get())->toBe("site_name: first\n");

    // A later remember() overwrites, it doesn't keep history.
    $remembered->remember("site_name: second\n");
    expect($remembered->get())->toBe("site_name: second\n");
});

test('the remembered file is not world-readable', function () {
    $remembered = app(RememberedConfig::class);
    $remembered->remember("site_name: secret\n");

    $perms = fileperms(storage_path('app/private/authentik/last-config.yml')) & 0777;
    expect($perms)->toBe(0600);
});
