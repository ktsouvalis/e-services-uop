<?php

use App\Jobs\Mailers\SendMailerFile;
use App\Mail\MailToDepartment;
use App\Models\City;
use App\Models\Department;
use App\Models\Mailer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use romanzipp\QueueMonitor\Models\Monitor;

beforeEach(function () {
    enableMenu('mailers');
});

function mailerSendBatchSessionKey(Mailer $mailer): string
{
    return "mailers.send_batch.{$mailer->id}";
}

test('guests are redirected to login', function () {
    $mailer = Mailer::factory()->create();

    $this->get(route('mailers.index'))->assertRedirect(route('login'));
    $this->get(route('mailers.edit', $mailer))->assertRedirect(route('login'));
});

test('access is denied when the mailers menu is disabled', function () {
    disableMenu('mailers');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('mailers.index'))->assertForbidden();
});

test('index only lists public mailers and the users own private mailers', function () {
    $user = User::factory()->create();
    $own = Mailer::factory()->create(['user_id' => $user->id, 'is_public' => false]);
    $public = Mailer::factory()->public()->create();
    $othersPrivate = Mailer::factory()->create(['is_public' => false]);

    $response = $this->actingAs($user)->get(route('mailers.index'));

    $response->assertOk();
    $mailers = $response->viewData('mailers');
    expect($mailers->pluck('id'))->toContain($own->id, $public->id)
        ->not->toContain($othersPrivate->id);
});

test('a user can create a mailer they own', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('mailers.store'), [
        'name' => 'Announcement batch',
    ]);

    $mailer = Mailer::first();
    $response->assertRedirect(route('mailers.edit', $mailer->id));
    expect($mailer->user_id)->toBe($user->id);
    expect($mailer->is_public)->toBeFalse();
});

test('only the creator can edit a private mailer', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $mailer = Mailer::factory()->create(['user_id' => $owner->id, 'is_public' => false]);

    $this->actingAs($owner)->get(route('mailers.edit', $mailer))->assertOk();
    $this->actingAs($other)->get(route('mailers.edit', $mailer))->assertForbidden();
});

test('any authenticated user can edit a public mailer but only the creator can delete it', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $mailer = Mailer::factory()->public()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->get(route('mailers.edit', $mailer))->assertOk();
    $this->actingAs($other)->delete(route('mailers.destroy', $mailer))->assertForbidden();
    $this->actingAs($owner)->delete(route('mailers.destroy', $mailer))->assertRedirect(route('mailers.index'));
    $this->assertModelMissing($mailer);
});

test('only the creator can toggle is_public even via a direct update payload', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $mailer = Mailer::factory()->public()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->patch(route('mailers.update', $mailer), [
        'name' => $mailer->name,
        'subject' => 'Subject',
        'is_public' => '0',
    ]);

    expect($mailer->fresh()->is_public)->toBeTrue();
});

test('mailer body is stripped of disallowed html tags on update', function () {
    $owner = User::factory()->create();
    $mailer = Mailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->patch(route('mailers.update', $mailer), [
        'name' => $mailer->name,
        'subject' => 'Subject',
        'body' => '<p>Hello</p><script>alert(1)</script>',
    ]);

    expect($mailer->fresh()->body)->toBe('<p>Hello</p>alert(1)');
});

test('a user can upload and then delete a file on their own mailer', function () {
    Storage::fake();
    $owner = User::factory()->create();
    $mailer = Mailer::factory()->create(['user_id' => $owner->id]);
    $file = UploadedFile::fake()->create('001 - report.pdf', 10);

    $this->actingAs($owner)->post(route('mailers.upload_files', $mailer), [
        'files' => [$file],
    ])->assertRedirect();

    $mailer->refresh();
    expect($mailer->getFilesCount())->toBe(1);
    Storage::disk('local')->assertExists("mailers/{$mailer->id}/001 - report.pdf");

    $index = $mailer->files[0]['index'];
    $this->actingAs($owner)->post(route('mailers.delete_file', ['mailer' => $mailer, 'index' => $index]))
        ->assertRedirect();

    expect($mailer->fresh()->getFilesCount())->toBe(0);
    Storage::disk('local')->assertMissing("mailers/{$mailer->id}/001 - report.pdf");
});

test('a non-owner cannot upload files to a private mailer', function () {
    Storage::fake();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $mailer = Mailer::factory()->create(['user_id' => $owner->id, 'is_public' => false]);
    $file = UploadedFile::fake()->create('doc.pdf', 10);

    $this->actingAs($other)->post(route('mailers.upload_files', $mailer), [
        'files' => [$file],
    ])->assertForbidden();
});

test('sending a mailer file to a department dispatches SendMailerFile and sends MailToDepartment', function () {
    Mail::fake();
    Storage::fake();
    $owner = User::factory()->create();
    $city = City::factory()->create();
    $department = Department::factory()->create(['city_id' => $city->id]);
    $mailer = Mailer::factory()->create([
        'user_id' => $owner->id,
        'files' => [['index' => 0, 'filename' => 'report.pdf']],
    ]);
    Storage::disk('local')->put("mailers/{$mailer->id}/report.pdf", 'contents');

    $this->actingAs($owner)
        ->post(route('mailers.send', ['mailer' => $mailer, 'index' => 0, 'department' => $department->id]))
        ->assertRedirect();

    Mail::assertSent(MailToDepartment::class);
});

test('sending tracks the department and file on the queue-monitor row for the SendMailerFile job', function () {
    Mail::fake();
    Storage::fake();
    $owner = User::factory()->create();
    $city = City::factory()->create();
    $department = Department::factory()->create(['city_id' => $city->id, 'name' => 'Finance']);
    $mailer = Mailer::factory()->create([
        'user_id' => $owner->id,
        'files' => [['index' => 0, 'filename' => 'report.pdf']],
    ]);
    Storage::disk('local')->put("mailers/{$mailer->id}/report.pdf", 'contents');

    $this->actingAs($owner)
        ->post(route('mailers.send', ['mailer' => $mailer, 'index' => 0, 'department' => $department->id]));

    $monitor = Monitor::where('name', SendMailerFile::class)->first();

    expect($monitor)->not->toBeNull();
    expect($monitor->getData())->toHaveKey('department', 'Finance');
    expect($monitor->getData())->toHaveKey('file', 'report.pdf');
});

test('a non-owner cannot send from a private mailer', function () {
    Storage::fake();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $city = City::factory()->create();
    $department = Department::factory()->create(['city_id' => $city->id]);
    $mailer = Mailer::factory()->create([
        'user_id' => $owner->id,
        'is_public' => false,
        'files' => [['index' => 0, 'filename' => 'report.pdf']],
    ]);
    Storage::disk('local')->put("mailers/{$mailer->id}/report.pdf", 'contents');

    $this->actingAs($other)
        ->post(route('mailers.send', ['mailer' => $mailer, 'index' => 0, 'department' => $department->id]))
        ->assertForbidden();
});

test('review matches files to departments by numeric filename prefix and flags unmatched files', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create();
    $department = Department::factory()->create(['id' => 501, 'city_id' => $city->id]);
    $mailer = Mailer::factory()->create([
        'user_id' => $owner->id,
        'files' => [
            ['index' => 0, 'filename' => '501 - matched.pdf'],
            ['index' => 1, 'filename' => 'no-prefix.pdf'],
        ],
    ]);

    $response = $this->actingAs($owner)->get(route('mailers.review', $mailer));

    $response->assertOk();
    $reviewArray = $response->viewData('review_array');
    expect($reviewArray[0]['to'])->toBeInstanceOf(Department::class)
        ->and($reviewArray[0]['to']->id)->toBe($department->id);
    expect($reviewArray[1]['to'])->toBe('Department not found');
});

test('review matches a file to its department by the 3-digit academic code leading the filename', function () {
    // departments.id is not an autoincrement surrogate key - it IS the
    // Ministry-assigned academic code for the department (e.g. 098 for
    // Πληροφορικής και Τηλεπικοινωνιών, Τρίπολη), zero-padded to 3 digits.
    // The filename convention real staff use is "<code> - <department name>...".
    $owner = User::factory()->create();
    $city = City::factory()->create();
    $department = Department::factory()->create([
        'id' => 98,
        'city_id' => $city->id,
        'name' => 'ΠΛΗΡΟΦΟΡΙΚΗΣ ΚΑΙ ΤΗΛΕΠΙΚΟΙΝΩΝΙΩΝ (ΤΡΙΠΟΛΗ)',
    ]);
    $mailer = Mailer::factory()->create([
        'user_id' => $owner->id,
        'files' => [
            ['index' => 0, 'filename' => '098 - ΠΛΗΡΟΦΟΡΙΚΗΣ ΚΑΙ ΤΗΛΕΠΙΚΟΙΝΩΝΙΩΝ (ΤΡΙΠΟΛΗ)_ΕΓΓΡΑΦΕΣ_2024.txt'],
        ],
    ]);

    $reviewArray = $this->actingAs($owner)->get(route('mailers.review', $mailer))
        ->assertOk()
        ->viewData('review_array');

    expect($reviewArray[0]['to'])->toBeInstanceOf(Department::class)
        ->and($reviewArray[0]['to']->id)->toBe(98)
        ->and($reviewArray[0]['to']->id)->toBe($department->id);
});

test('review matches a file to its department by a 4-digit academic code leading the filename', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create();
    $department = Department::factory()->create(['id' => 1046, 'city_id' => $city->id]);
    $mailer = Mailer::factory()->create([
        'user_id' => $owner->id,
        'files' => [
            ['index' => 0, 'filename' => '1046 - matched.txt'],
        ],
    ]);

    $reviewArray = $this->actingAs($owner)->get(route('mailers.review', $mailer))
        ->assertOk()
        ->viewData('review_array');

    expect($reviewArray[0]['to'])->toBeInstanceOf(Department::class)
        ->and($reviewArray[0]['to']->id)->toBe(1046);
});

test('review flags a code that does not correspond to any department as not found', function () {
    $owner = User::factory()->create();
    // No department with id 999 or 666 exists - these codes are made up for
    // this test and must not accidentally match an unrelated department.
    $mailer = Mailer::factory()->create([
        'user_id' => $owner->id,
        'files' => [
            ['index' => 0, 'filename' => '999 - ΑΓΝΩΣΤΟ ΤΜΗΜΑ (ΔΕΝ ΥΠΑΡΧΕΙ)_TEST_2024.txt'],
            ['index' => 1, 'filename' => '666 - ΔΟΚΙΜΑΣΤΙΚΟ ΤΜΗΜΑ (ΔΕΝ ΥΠΑΡΧΕΙ)_TEST_2024.txt'],
        ],
    ]);

    $reviewArray = $this->actingAs($owner)->get(route('mailers.review', $mailer))
        ->assertOk()
        ->viewData('review_array');

    expect($reviewArray[0]['to'])->toBe('Department not found');
    expect($reviewArray[1]['to'])->toBe('Department not found');
});

test('visiting review on a mailer with no files redirects back instead of crashing', function () {
    $owner = User::factory()->create();
    $mailer = Mailer::factory()->create(['user_id' => $owner->id, 'files' => null]);

    $this->actingAs($owner)->get(route('mailers.review', $mailer))
        ->assertRedirect(route('mailers.edit', $mailer))
        ->assertSessionHas('error');
});

test('send_all dispatches a batch that sends to every matched department and skips unmatched files', function () {
    Mail::fake();
    Storage::fake();
    $owner = User::factory()->create();
    $city = City::factory()->create();
    $department = Department::factory()->create(['id' => 502, 'city_id' => $city->id]);
    $mailer = Mailer::factory()->create([
        'user_id' => $owner->id,
        'files' => [
            ['index' => 0, 'filename' => '502 - matched.pdf'],
            ['index' => 1, 'filename' => 'no-prefix.pdf'],
        ],
    ]);
    Storage::disk('local')->put("mailers/{$mailer->id}/502 - matched.pdf", 'contents');
    Storage::disk('local')->put("mailers/{$mailer->id}/no-prefix.pdf", 'contents');

    // review() must be visited first - it's what stages the department match in session.
    $this->actingAs($owner)->get(route('mailers.review', $mailer));

    $response = $this->actingAs($owner)->post(route('mailers.send_all', $mailer));

    $response->assertRedirect(route('mailers.review', $mailer))
        ->assertSessionHas('success');

    Mail::assertSent(MailToDepartment::class, 1);

    $batchId = session(mailerSendBatchSessionKey($mailer));
    expect($batchId)->not->toBeNull();
});

test('send-status reports a finished batch and is scoped to the mailer that started it', function () {
    Mail::fake();
    Storage::fake();
    $owner = User::factory()->create();
    $city = City::factory()->create();
    $department = Department::factory()->create(['id' => 503, 'city_id' => $city->id]);
    $mailer = Mailer::factory()->create([
        'user_id' => $owner->id,
        'files' => [['index' => 0, 'filename' => '503 - matched.pdf']],
    ]);
    $other = Mailer::factory()->create(['user_id' => $owner->id]);
    Storage::disk('local')->put("mailers/{$mailer->id}/503 - matched.pdf", 'contents');

    $this->actingAs($owner)->get(route('mailers.review', $mailer));
    $this->actingAs($owner)->post(route('mailers.send_all', $mailer));

    $batchId = session(mailerSendBatchSessionKey($mailer));
    expect($batchId)->not->toBeNull();

    $this->actingAs($owner)->get(route('mailers.send-status', [$mailer, $batchId]))
        ->assertOk()
        ->assertJson(['finished' => true, 'total' => 1, 'processed' => 1, 'failed' => 0]);

    // A batch id that was never stored for $other must not resolve, even though
    // it's a real, valid batch id (just for a different mailer's session).
    $this->actingAs($owner)->get(route('mailers.send-status', [$other, $batchId]))
        ->assertNotFound();
});

test('review data for one mailer does not leak into another mailer\'s send_all', function () {
    Mail::fake();
    Storage::fake();
    $owner = User::factory()->create();
    $city = City::factory()->create();
    $departmentA = Department::factory()->create(['id' => 504, 'city_id' => $city->id]);
    $a = Mailer::factory()->create([
        'user_id' => $owner->id,
        'files' => [['index' => 0, 'filename' => '504 - matched.pdf']],
    ]);
    $b = Mailer::factory()->create(['user_id' => $owner->id, 'files' => null]);
    Storage::disk('local')->put("mailers/{$a->id}/504 - matched.pdf", 'contents');

    // Stage a's review data, then try to send_all on b, which was never reviewed.
    $this->actingAs($owner)->get(route('mailers.review', $a));

    $this->actingAs($owner)->post(route('mailers.send_all', $b))
        ->assertRedirect()
        ->assertSessionHas('warning');

    Mail::assertNothingSent();
});

test('the create and show resource routes are not registered', function () {
    $owner = User::factory()->create();
    $mailer = Mailer::factory()->create(['user_id' => $owner->id]);

    // Both URIs have the same single-segment shape as update/destroy's
    // (PUT/PATCH/DELETE) "/mailers/{mailer}", so GET on either is a method
    // mismatch (405) rather than an unmatched route (404) - the key regression
    // check is that neither reaches MailerController::create()/show(), which
    // don't exist and would otherwise fatal with a 500.
    $this->actingAs($owner)->get('/mailers/create')->assertStatus(405);
    $this->actingAs($owner)->get('/mailers/' . $mailer->id)->assertStatus(405);
});
