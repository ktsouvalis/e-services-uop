<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to login', function () {
    $this->get(route('log-reader'))->assertRedirect(route('login'));
    $this->post(route('log-reader.read-logs'))->assertRedirect(route('login'));
});

test('access is denied when there is no enabled log-reader menu row', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)->get(route('log-reader'))->assertForbidden();
});

test('access is denied when the log-reader menu is disabled', function () {
    disableMenu('log-reader');
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)->get(route('log-reader'))->assertForbidden();
});

test('an enabled menu allows access to the page', function () {
    enableMenu('log-reader');
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)->get(route('log-reader'))->assertOk();
});

test('submitting without a file returns a warning', function () {
    enableMenu('log-reader');
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->post(route('log-reader.read-logs'), ['regex' => 'foo'])
        ->assertRedirect()
        ->assertSessionHas('warning');
});

test('submitting without a regex returns a warning', function () {
    enableMenu('log-reader');
    Storage::fake();
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->post(route('log-reader.read-logs'), [
            'file' => UploadedFile::fake()->create('app.log', 5, 'text/plain'),
        ])
        ->assertRedirect()
        ->assertSessionHas('warning');
});

test('a valid file and regex produces an xlsx download of unique matches', function () {
    // Not using Storage::fake(): the controller reads the uploaded file back via a raw
    // storage_path()/file_get_contents() call rather than the Storage facade, so a faked
    // disk (which redirects to a separate testing directory) would make that read 404.
    enableMenu('log-reader');
    $user = \App\Models\User::factory()->create();

    $content = "user=alice action=login\nuser=bob action=login\nuser=alice action=login\n";
    $file = UploadedFile::fake()->createWithContent('app.log', $content);

    $response = $this->actingAs($user)->post(route('log-reader.read-logs'), [
        'file' => $file,
        'regex' => 'user=\\w+',
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    // deleteFileAfterSend()'s terminating callback doesn't run under the test HTTP
    // kernel, so the generated xlsx is left behind alongside the uploaded log - clean
    // up both rather than leaving artifacts in storage/app/private/log-reader/.
    @unlink(storage_path('app/private/log-reader/app.log'));
    @unlink(storage_path('app/private/log-reader/app.xlsx'));
});
