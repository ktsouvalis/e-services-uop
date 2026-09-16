<?php

use App\Jobs\Sheetmailers\SendSheetmailerEmail;
use App\Mail\MailSheetMailer;
use App\Models\Sheetmailer;
use App\Models\User;
use App\Services\DeliveryLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use romanzipp\QueueMonitor\Models\Monitor;

beforeEach(function () {
    enableMenu('sheetmailers');
    // Delivery logs are written to the real filesystem (see App\Services\DeliveryLog),
    // not the fake disk - and sheetmailer ids restart from 1 every test under sqlite
    // :memory: RefreshDatabase, so a previous test's log files would otherwise
    // leak into this one's directory.
    File::deleteDirectory(storage_path('app/private/sheetmailers/logs'));
});

function fakeEmailXlsxUpload(): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'email');
    $sheet->setCellValue('B1', 'place1');
    $sheet->setCellValue('A2', 'valid@uop.gr');
    $sheet->setCellValue('B2', 'Extra 1');
    $sheet->setCellValue('A3', 'not-an-email');
    $sheet->setCellValue('B3', 'Extra 2');

    $path = tempnam(sys_get_temp_dir(), 'sheetmailer') . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'emails.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function xlsxUploadMissingEmailHeader(): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'recipient');
    $sheet->setCellValue('B1', 'place1');
    $sheet->setCellValue('A2', 'valid@uop.gr');
    $sheet->setCellValue('B2', 'Extra 1');

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

test('sheetmailer signature is stripped of disallowed html tags but keeps allowed formatting on update', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->patch(route('sheetmailers.update', $sheetmailer), [
        'name' => $sheetmailer->name,
        'signature' => '<em>Best regards</em><script>alert(1)</script>',
    ]);

    expect($sheetmailer->fresh()->signature)->toBe('<em>Best regards</em>alert(1)');
});

test('the signature is rendered as HTML (not escaped) in the sent mail, so formatting like <em> actually applies', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create([
        'user_id' => $owner->id,
        'signature' => '<em>Best regards</em>',
    ]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [['email' => 'one@uop.gr', 'placeholders' => []]],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    Mail::assertSent(MailSheetMailer::class, fn ($mailable) => str_contains($mailable->signature, '<em>Best regards</em>'));
});

test('uploading an xlsx splits rows into eligible emails and non-emails in session, keyed by header', function () {
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
    expect($recipients['emails'][0]['placeholders'])->toBe(['place1' => 'Extra 1']);
    expect($recipients['non_emails'])->toContain('not-an-email');
});

test('uploading an xlsx pairs each row\'s email with that same row\'s placeholder data, not another row\'s', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'email');
    $sheet->setCellValue('B1', 'place1');
    $sheet->setCellValue('C1', 'place2');
    $sheet->setCellValue('A2', 'alice@uop.gr');
    $sheet->setCellValue('B2', 'Alice');
    $sheet->setCellValue('C2', 'alice01');
    $sheet->setCellValue('A3', 'bob@uop.gr');
    $sheet->setCellValue('B3', 'Bob');
    $sheet->setCellValue('C3', 'bob02');
    $sheet->setCellValue('A4', 'carol@uop.gr');
    $sheet->setCellValue('B4', 'Carol');
    $sheet->setCellValue('C4', 'carol03');

    $path = tempnam(sys_get_temp_dir(), 'sheetmailer') . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $file = new UploadedFile($path, 'emails.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($owner)->post(route('sheetmailers.upload_file', $sheetmailer), ['file' => $file]);

    $emails = session(recipientsSessionKey($sheetmailer))['emails'];
    expect($emails)->toHaveCount(3);

    $byEmail = collect($emails)->keyBy('email');
    expect($byEmail['alice@uop.gr']['placeholders'])->toBe(['place1' => 'Alice', 'place2' => 'alice01']);
    expect($byEmail['bob@uop.gr']['placeholders'])->toBe(['place1' => 'Bob', 'place2' => 'bob02']);
    expect($byEmail['carol@uop.gr']['placeholders'])->toBe(['place1' => 'Carol', 'place2' => 'carol03']);
});

test('the "email" column can be in any position and rows still pair correctly with their own data', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    // "email" is the middle column here, not column A.
    $sheet->setCellValue('A1', 'place1');
    $sheet->setCellValue('B1', 'email');
    $sheet->setCellValue('C1', 'place2');
    $sheet->setCellValue('A2', 'Alice');
    $sheet->setCellValue('B2', 'alice@uop.gr');
    $sheet->setCellValue('C2', 'alice01');
    $sheet->setCellValue('A3', 'Bob');
    $sheet->setCellValue('B3', 'bob@uop.gr');
    $sheet->setCellValue('C3', 'bob02');

    $path = tempnam(sys_get_temp_dir(), 'sheetmailer') . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $file = new UploadedFile($path, 'emails.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($owner)->post(route('sheetmailers.upload_file', $sheetmailer), ['file' => $file]);

    $byEmail = collect(session(recipientsSessionKey($sheetmailer))['emails'])->keyBy('email');
    expect($byEmail['alice@uop.gr']['placeholders'])->toBe(['place1' => 'Alice', 'place2' => 'alice01']);
    expect($byEmail['bob@uop.gr']['placeholders'])->toBe(['place1' => 'Bob', 'place2' => 'bob02']);
});

test('uploading an xlsx without an "email" column header is rejected with a friendly error', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->post(route('sheetmailers.upload_file', $sheetmailer), [
        'file' => xlsxUploadMissingEmailHeader(),
    ]);

    $response->assertRedirect()->assertSessionHas('error');
    expect(session('error'))->toContain('email');
    $response->assertSessionMissing(recipientsSessionKey($sheetmailer));
});

test('uploading an xlsx strips html tags out of placeholder column values', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'email');
    $sheet->setCellValue('B1', 'place1');
    $sheet->setCellValue('A2', 'valid@uop.gr');
    $sheet->setCellValue('B2', '<script>alert(1)</script><b>Bob</b>');

    $path = tempnam(sys_get_temp_dir(), 'sheetmailer') . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $file = new UploadedFile($path, 'emails.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($owner)->post(route('sheetmailers.upload_file', $sheetmailer), ['file' => $file]);

    $recipients = session(recipientsSessionKey($sheetmailer));
    expect($recipients['emails'][0]['placeholders']['place1'])->toBe('alert(1)Bob');
});

test('uploading a non-xlsx file is rejected by validation', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('sheetmailers.upload_file', $sheetmailer), [
        'file' => UploadedFile::fake()->create('list.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('file');
});

test('comma separated emails are split into eligible and non-emails, with no placeholder data', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('sheetmailers.comma_mails', $sheetmailer), [
        'comma_mails' => 'good@uop.gr, also-good@uop.gr, not-valid',
    ])->assertRedirect(route('sheetmailers.confirm', $sheetmailer));

    $recipients = session(recipientsSessionKey($sheetmailer));
    expect($recipients['emailCount'])->toBe(2);
    expect($recipients['non_emails'])->toBe(['not-valid']);
    expect($recipients['emails'][0]['placeholders'])->toBe([]);
});

function recipientsSessionKey(Sheetmailer $sheetmailer): string
{
    return "sheetmailers.recipients.{$sheetmailer->id}";
}

test('sending merges each recipient\'s placeholders into the subject and body', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create([
        'user_id' => $owner->id,
        'subject' => 'Hello {{place1}}',
        'body' => 'Dear {{place1}}, your username is {{place2}}.',
    ]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'Alice', 'place2' => 'alice01']],
            ],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    Mail::assertSent(MailSheetMailer::class, function ($mailable) {
        return $mailable->envelope()->subject === 'Hello Alice'
            && $mailable->body === 'Dear Alice, your username is alice01.';
    });
});

test('the full upload-to-send pipeline keeps each recipient paired with their own row\'s data', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create([
        'user_id' => $owner->id,
        'subject' => 'Hello {{place1}}',
        'body' => 'Dear {{place1}}, your username is {{place2}}.',
    ]);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'email');
    $sheet->setCellValue('B1', 'place1');
    $sheet->setCellValue('C1', 'place2');
    $sheet->setCellValue('A2', 'alice@uop.gr');
    $sheet->setCellValue('B2', 'Alice');
    $sheet->setCellValue('C2', 'alice01');
    $sheet->setCellValue('A3', 'bob@uop.gr');
    $sheet->setCellValue('B3', 'Bob');
    $sheet->setCellValue('C3', 'bob02');

    $path = tempnam(sys_get_temp_dir(), 'sheetmailer') . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $file = new UploadedFile($path, 'emails.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($owner)->post(route('sheetmailers.upload_file', $sheetmailer), ['file' => $file]);
    $this->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    Mail::assertSent(MailSheetMailer::class, fn ($mailable) => $mailable->hasTo('alice@uop.gr')
        && $mailable->envelope()->subject === 'Hello Alice'
        && $mailable->body === 'Dear Alice, your username is alice01.');

    Mail::assertSent(MailSheetMailer::class, fn ($mailable) => $mailable->hasTo('bob@uop.gr')
        && $mailable->envelope()->subject === 'Hello Bob'
        && $mailable->body === 'Dear Bob, your username is bob02.');
});

test('a placeholder still gets replaced even when formatting was applied to only part of the token', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create([
        'user_id' => $owner->id,
        'subject' => 'Hello there',
        // Selecting only the closing "}}" and hitting italic in the rich-text editor
        // stores exactly this shape: one "}" plain, the other wrapped in <em>, splitting
        // the token's raw text across a tag boundary.
        'body' => 'Dear {{place1}<em>}</em>, welcome.',
    ]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'Alice']],
            ],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    Mail::assertSent(MailSheetMailer::class, fn ($mailable) => $mailable->body === 'Dear Alice, welcome.');
});

test('a whole token wrapped consistently in one formatting tag keeps that formatting around the replaced value', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create([
        'user_id' => $owner->id,
        'subject' => 'Hello there',
        // The whole "{{place1}}" token was selected together and bolded, so the tag
        // wraps it entirely rather than splitting it - the bold tag must stay around
        // the substituted value, not get swallowed as if it were a stray split-tag.
        'body' => 'Dear <strong>{{place1}}</strong>, welcome.',
    ]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'Alice']],
            ],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    Mail::assertSent(MailSheetMailer::class, fn ($mailable) => $mailable->body === 'Dear <strong>Alice</strong>, welcome.');
});

test('a placeholder with no matching column is left untouched in the sent mail', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create([
        'user_id' => $owner->id,
        'subject' => 'Hi {{place1}}',
        'body' => 'Typo column: {{unknown}}',
    ]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'Alice']],
            ],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    Mail::assertSent(MailSheetMailer::class, fn ($mailable) => $mailable->body === 'Typo column: {{unknown}}');
});

test('sending dispatches a batch that sends every eligible email in session', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $response = $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']],
                ['email' => 'two@uop.gr', 'placeholders' => ['place1' => 'B']],
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

test('sending writes one delivery log covering every recipient, listed and downloadable on edit', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']],
                ['email' => 'two@uop.gr', 'placeholders' => ['place1' => 'B']],
            ],
            'non_emails' => [],
            'emailCount' => 2,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    $response = $this->actingAs($owner)->get(route('sheetmailers.edit', $sheetmailer));
    $response->assertOk();
    $response->assertViewHas('deliveryLogs', fn ($logs) => count($logs) === 1
        && str_starts_with($logs[0]['filename'], "mail_delivery_sheetmailer_{$sheetmailer->id}_"));

    $filename = $response->viewData('deliveryLogs')[0]['filename'];

    $this->actingAs($owner)
        ->get(route('sheetmailers.download-log', ['sheetmailer' => $sheetmailer, 'filename' => $filename]))
        ->assertOk();

    $content = file_get_contents(storage_path("app/private/sheetmailers/logs/{$sheetmailer->id}/{$filename}"));
    expect($content)->toContain('one@uop.gr')->toContain('two@uop.gr');
});

test('a non-owner cannot download a private sheetmailer\'s delivery log', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id, 'is_public' => false]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']]],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    $filename = DeliveryLog::listFor('sheetmailer', $sheetmailer->id)[0]['filename'];

    $this->actingAs($other)
        ->get(route('sheetmailers.download-log', ['sheetmailer' => $sheetmailer, 'filename' => $filename]))
        ->assertForbidden();
});

test('downloading a sheetmailer delivery log rejects a path-traversal filename', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('sheetmailers.download-log', ['sheetmailer' => $sheetmailer, 'filename' => '../secret.log']))
        ->assertNotFound();
});

test('sending tracks the recipient email on the queue-monitor row for each SendSheetmailerEmail job', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']],
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
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']],
                ['email' => 'two@uop.gr', 'placeholders' => ['place1' => 'B']],
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
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']],
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
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']],
                ['email' => 'two@uop.gr', 'placeholders' => ['place1' => 'B']],
                ['email' => 'three@uop.gr', 'placeholders' => ['place1' => 'C']],
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
    Mail::assertNotSent(MailSheetMailer::class, fn ($mailable) => $mailable->placeholders === ['place1' => 'B']);
});

test('sending with every recipient unchecked sends nothing and keeps the staged list for another try', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $response = $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']],
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

test('confirm shows one table column per placeholder found in the uploaded spreadsheet', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('sheetmailers.upload_file', $sheetmailer), [
        'file' => fakeEmailXlsxUpload(),
    ]);

    $this->actingAs($owner)->get(route('sheetmailers.confirm', $sheetmailer))
        ->assertOk()
        ->assertViewHas('placeholderKeys', ['place1'])
        ->assertSee('{{place1}}')
        ->assertSee('Extra 1');
});

test('dry run renders the first, middle and last staged recipient and sends no mail', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $response = $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']],
                ['email' => 'two@uop.gr', 'placeholders' => ['place1' => 'B']],
                ['email' => 'three@uop.gr', 'placeholders' => ['place1' => 'C']],
                ['email' => 'four@uop.gr', 'placeholders' => ['place1' => 'D']],
                ['email' => 'five@uop.gr', 'placeholders' => ['place1' => 'E']],
            ],
            'non_emails' => [],
            'emailCount' => 5,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.dry-run', $sheetmailer));

    $response->assertRedirect(route('sheetmailers.confirm', $sheetmailer))
        ->assertSessionHas('success');

    // Dry run must never actually mail anyone.
    Mail::assertNothingSent();
    Mail::assertNothingQueued();

    // The staged list is a dry run, not a send - it must survive so Confirm
    // still has something to show right after.
    $response->assertSessionHas(recipientsSessionKey($sheetmailer));
});

test('dry run de-duplicates the sample when there are fewer than three staged recipients', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'only@uop.gr', 'placeholders' => ['place1' => 'A']],
            ],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ])->actingAs($owner)->post(route('sheetmailers.dry-run', $sheetmailer))
        ->assertRedirect(route('sheetmailers.confirm', $sheetmailer))
        ->assertSessionHas('success');

    Mail::assertNothingSent();
});

test('dry run without a staged recipient list redirects back with an error instead of crashing', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('sheetmailers.dry-run', $sheetmailer))
        ->assertRedirect(route('sheetmailers.edit', $sheetmailer))
        ->assertSessionHas('error');
});

test('dry run is blocked while the menu is disabled, even for the owner', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'A']]],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ]);

    disableMenu('sheetmailers');

    $this->actingAs($owner)->post(route('sheetmailers.dry-run', $sheetmailer))->assertForbidden();

    Mail::assertNothingSent();
});

test('previewing a recipient returns their fully merged subject, body and signature separately, without sending', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create([
        'user_id' => $owner->id,
        'subject' => 'Hello {{place1}}',
        'body' => 'Dear {{place1}}, your username is {{place2}}.',
        'signature' => '<em>Best regards</em>',
    ]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [
                ['email' => 'one@uop.gr', 'placeholders' => ['place1' => 'Alice', 'place2' => 'alice01']],
                ['email' => 'two@uop.gr', 'placeholders' => ['place1' => 'Bob', 'place2' => 'bob02']],
            ],
            'non_emails' => [],
            'emailCount' => 2,
        ],
    ]);

    $this->actingAs($owner)->get(route('sheetmailers.preview-recipient', [$sheetmailer, 1]))
        ->assertOk()
        ->assertJson([
            'email' => 'two@uop.gr',
            'subject' => 'Hello Bob',
            'body' => 'Dear Bob, your username is bob02.',
            'signature' => '<em>Best regards</em>',
        ]);

    Mail::assertNothingSent();
});

test('previewing an out-of-range recipient index 404s instead of crashing', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [['email' => 'one@uop.gr', 'placeholders' => []]],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ]);

    $this->actingAs($owner)->get(route('sheetmailers.preview-recipient', [$sheetmailer, 5]))->assertNotFound();
});

test('previewing a recipient is blocked while the menu is disabled, even for the owner', function () {
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $this->withSession([
        recipientsSessionKey($sheetmailer) => [
            'emails' => [['email' => 'one@uop.gr', 'placeholders' => []]],
            'non_emails' => [],
            'emailCount' => 1,
        ],
    ]);

    disableMenu('sheetmailers');

    $this->actingAs($owner)->get(route('sheetmailers.preview-recipient', [$sheetmailer, 0]))->assertForbidden();
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
