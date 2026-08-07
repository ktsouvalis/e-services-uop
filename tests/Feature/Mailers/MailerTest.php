<?php

use App\Mail\MailToDepartment;
use App\Models\City;
use App\Models\Department;
use App\Models\Mailer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    enableMenu('mailers');
});

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

test('only the creator can flip is_public even via a direct update payload', function () {
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

test('sending a mailer file to a department queues MailToDepartment', function () {
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

    Mail::assertQueued(MailToDepartment::class);
});
