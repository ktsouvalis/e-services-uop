<?php

use App\Jobs\Sheetmailers\SendSheetmailerEmail;
use App\Mail\MailSheetMailer;
use App\Models\Sheetmailer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use romanzipp\QueueMonitor\Models\Monitor;

beforeEach(function () {
    enableMenu('sheetmailers');
});

function fakeEmailXlsxUpload(): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'valid@uop.gr');
    $sheet->setCellValue('B1', 'Extra 1');
    $sheet->setCellValue('A2', 'not-an-email');
    $sheet->setCellValue('B2', 'Extra 2');

    $path = tempnam(sys_get_temp_dir(), 'sheetmailer') . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'emails.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('guests are redirected to login', function () {
    $sheetmailer = Sheetmailer::factory()->create();

    $this->get(route('sheetmailers.index'))->assertRedirect(route('login'));
    $this->get(route('sheetmailers.edit', $sheetmailer))->assertRedirect(route('login'));
});

test('menu disabled forbids index, edit and update', function () {
    disableMenu('sheetmailers');
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get(route('sheetmailers.index'))->assertForbidden();
    $this->actingAs($owner)->get(route('sheetmailers.edit', $sheetmailer))->assertForbidden();
    $this->actingAs($owner)->patch(route('sheetmailers.update', $sheetmailer), ['name' => 'x'])->assertForbidden();
});

test('index only lists public sheetmailers and the users own private ones', function () {
    $user = User::factory()->create();
    $own = Sheetmailer::factory()->create(['user_id' => $user->id, 'is_public' => false]);
    $public = Sheetmailer::factory()->public()->create();
    $othersPrivate = Sheetmailer::factory()->create(['is_public' => false]);

    $response = $this->actingAs($user)->get(route('sheetmailers.index'));

    $sheetmailers = $response->viewData('sheetmailers');
    expect($sheetmailers->pluck('id'))->toContain($own->id, $public->id)
        ->not->toContain($othersPrivate->id);
});

test('a user can create a sheetmailer they own', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('sheetmailers.store'), [
        'name' => 'Newsletter batch',
    ]);

    $sheetmailer = Sheetmailer::first();
    $response->assertRedirect(route('sheetmailers.edit', $sheetmailer->id));
    expect($sheetmailer->user_id)->toBe($user->id);
});

test('creating a sheetmailer without a name fails validation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sheetmailers.store'), [])
        ->assertSessionHasErrors('name');

    expect(Sheetmailer::count())->toBe(0);
});

test('only the creator can toggle is_public on update, even in the payload', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->public()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->patch(route('sheetmailers.update', $sheetmailer), [
        'name' => $sheetmailer->name,
        'is_public' => '0',
    ]);

    expect($sheetmailer->fresh()->is_public)->toBeTrue();

    $this->actingAs($owner)->patch(route('sheetmailers.update', $sheetmailer), [
        'name' => $sheetmailer->name,
        'is_public' => '0',
    ]);

    expect($sheetmailer->fresh()->is_public)->toBeFalse();
});

test('sheetmailer body is stripped of disallowed html tags on update', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->patch(route('sheetmailers.update', $sheetmailer), [
        'name' => $sheetmailer->name,
        'body' => '<p>Hello</p><script>alert(1)</script>',
    ]);

    expect($sheetmailer->fresh()->body)->toBe('<p>Hello</p>alert(1)');
});

test('uploading an xlsx splits rows into eligible emails and non-emails in session', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->post(route('sheetmailers.upload_file', $sheetmailer), [
        'file' => fakeEmailXlsxUpload(),
    ]);

    $response->assertRedirect(route('sheetmailers.confirm', $sheetmailer));
    $response->assertSessionHas(recipientsSessionKey($sheetmailer));
    $recipients = session(recipientsSessionKey($sheetmailer));
    expect($recipients['emailCount'])->toBe(1);
    expect($recipients['emails'][0]['email'])->toBe('valid@uop.gr');
    expect($recipients['non_emails'])->toContain('not-an-email');
});

test('uploading a non-xlsx file is rejected by validation', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('sheetmailers.upload_file', $sheetmailer), [
        'file' => UploadedFile::fake()->create('list.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('file');
});

test('comma separated emails are split into eligible and non-emails', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('sheetmailers.comma_mails', $sheetmailer), [
        'comma_mails' => 'good@uop.gr, also-good@uop.gr, not-valid',
    ])->assertRedirect(route('sheetmailers.confirm', $sheetmailer));

    $recipients = session(recipientsSessionKey($sheetmailer));
    expect($recipients['emailCount'])->toBe(2);
    expect($recipients['non_emails'])->toBe(['not-valid']);
});

function recipientsSessionKey(Sheetmailer $sheetmailer): string
{
    return "sheetmailers.recipients.{$sheetmailer->id}";
}

test('sending dispatches a batch that sends every eligible email in session', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $response = $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'additionalData' => 'A'],
                ['email' => 'two@uop.gr', 'additionalData' => 'B'],
            ],
            'non_emails' => [],
            'emailCount' => 2,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    $response->assertRedirect(route('sheetmailers.edit', $sheetmailer))
        ->assertSessionHas('success');

    // QUEUE_CONNECTION=sync in testing, so the batch's jobs already ran by
    // the time dispatch() returns - each one calls Mail::send(), not queue().
    Mail::assertSent(MailSheetMailer::class, 2);
    $response->assertSessionMissing(recipientsSessionKey($sheetmailer));
});

test('sending tracks the recipient email on the queue-monitor row for each SendSheetmailerEmail job', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'additionalData' => 'A'],
            ],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    $monitor = Monitor::where('name', SendSheetmailerEmail::class)->first();

    expect($monitor)->not->toBeNull();
    expect($monitor->getData())->toHaveKey('recipient', 'one@uop.gr');
});

test('send-status reports a finished batch and is scoped to the sheetmailer that started it', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);
    $other = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'additionalData' => 'A'],
                ['email' => 'two@uop.gr', 'additionalData' => 'B'],
            ],
            'non_emails' => [],
            'emailCount' => 2,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    $batchId = session(sendBatchSessionKey($sheetmailer));
    expect($batchId)->not->toBeNull();

    $this->actingAs($owner)->get(route('sheetmailers.send-status', [$sheetmailer, $batchId]))
        ->assertOk()
        ->assertJson(['finished' => true, 'total' => 2, 'processed' => 2, 'failed' => 0]);

    // A batch id that was never stored for $other must not resolve, even though
    // it's a real, valid batch id (just for a different sheetmailer's session).
    $this->actingAs($owner)->get(route('sheetmailers.send-status', [$other, $batchId]))
        ->assertNotFound();
});

function sendBatchSessionKey(Sheetmailer $sheetmailer): string
{
    return "sheetmailers.send_batch.{$sheetmailer->id}";
}

test('edit no longer shows send progress once the batch has finished', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'additionalData' => 'A'],
            ],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    // Sync queue means the batch is already finished by the time we reach
    // edit() again - it should self-clear rather than show a stale "done" bar.
    $this->actingAs($owner)->get(route('sheetmailers.edit', $sheetmailer))
        ->assertOk()
        ->assertViewHas('sendBatch', null);
});

test('the preview route no longer exists', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    expect(\Illuminate\Support\Facades\Route::has('sheetmailers.preview'))->toBeFalse();
    $this->actingAs($owner)->get('/sheetmailers/' . $sheetmailer->id . '/preview')->assertNotFound();
});

test('sending only sends to the recipients left checked on the confirm page', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $response = $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'additionalData' => 'A'],
                ['email' => 'two@uop.gr', 'additionalData' => 'B'],
                ['email' => 'three@uop.gr', 'additionalData' => 'C'],
            ],
            'non_emails' => [],
            'emailCount' => 3,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer), [
        'keep_present' => '1',
        'keep' => ['0', '2'],
    ]);

    $response->assertRedirect(route('sheetmailers.edit', $sheetmailer))
        ->assertSessionHas('success');

    Mail::assertSent(MailSheetMailer::class, 2);
    Mail::assertNotSent(MailSheetMailer::class, fn ($mailable) => $mailable->additionalData === 'B');
});

test('sending with every recipient unchecked sends nothing and keeps the staged list for another try', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $response = $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'additionalData' => 'A'],
            ],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer), [
        'keep_present' => '1',
    ]);

    $response->assertRedirect(route('sheetmailers.confirm', $sheetmailer))
        ->assertSessionHas('error');

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    $response->assertSessionHas(recipientsSessionKey($sheetmailer));
});

test('sending without a staged recipient list redirects back with an error instead of crashing', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer))
        ->assertRedirect(route('sheetmailers.edit', $sheetmailer))
        ->assertSessionHas('error');
});

test('visiting confirm without a staged recipient list redirects back with an error instead of crashing', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get(route('sheetmailers.confirm', $sheetmailer))
        ->assertRedirect(route('sheetmailers.edit', $sheetmailer))
        ->assertSessionHas('error');
});

test('staged recipients for one sheetmailer do not leak into another sheetmailer\'s confirm page', function () {
    $owner = User::factory()->create();
    $a = Sheetmailer::factory()->create(['user_id' => $owner->id]);
    $b = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('sheetmailers.comma_mails', $a), [
        'comma_mails' => 'a-recipient@uop.gr',
    ]);

    $this->actingAs($owner)->get(route('sheetmailers.confirm', $b))
        ->assertRedirect(route('sheetmailers.edit', $b))
        ->assertSessionHas('error');

    $this->actingAs($owner)->get(route('sheetmailers.confirm', $a))
        ->assertOk()
        ->assertViewHas('emails', function ($emails) {
            return collect($emails)->pluck('email')->contains('a-recipient@uop.gr');
        });
});

test('the create and show resource routes are not registered', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    // Both URIs have the same single-segment shape as update/destroy's
    // (PUT/PATCH/DELETE) "/sheetmailers/{sheetmailer}", so GET on either is a
    // method mismatch (405) rather than an unmatched route (404) - the key
    // regression check is that neither reaches SheetmailerController::create()/show(),
    // which don't exist and would otherwise fatal with a 500.
    $this->actingAs($owner)->get('/sheetmailers/create')->assertStatus(405);
    $this->actingAs($owner)->get('/sheetmailers/' . $sheetmailer->id)->assertStatus(405);
});
