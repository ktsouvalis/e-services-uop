<?php

use App\Mail\MailSheetMailer;
use App\Models\Sheetmailer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
    $response->assertSessionHas('emails');
    $response->assertSessionHas('non_emails');
    expect(session('emailCount'))->toBe(1);
    expect(session('emails')[0]['email'])->toBe('valid@uop.gr');
    expect(session('non_emails'))->toContain('not-an-email');
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

    expect(session('emailCount'))->toBe(2);
    expect(session('non_emails'))->toBe(['not-valid']);
});

test('sending queues a MailSheetMailer for every eligible email in session', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $sheetmailer = Sheetmailer::factory()->create(['user_id' => $owner->id]);

    $response = $this->withSession([
        'emails' => [
            ['email' => 'one@uop.gr', 'additionalData' => 'A'],
            ['email' => 'two@uop.gr', 'additionalData' => 'B'],
        ],
    ])->actingAs($owner)->post(route('sheetmailers.send', $sheetmailer));

    $response->assertRedirect(route('sheetmailers.edit', $sheetmailer))
        ->assertSessionHas('success');

    Mail::assertQueued(MailSheetMailer::class, 2);
    $response->assertSessionMissing('emails');
});
