<?php

use App\Models\AImodel;
use App\Models\Chatbot;
use App\Models\User;

beforeEach(function () {
    enableMenu('chatbots');
});

test('guests are redirected to login', function () {
    $chatbot = Chatbot::factory()->create();

    $this->get(route('chatbots.index'))->assertRedirect(route('login'));
    $this->get(route('chatbots.show', $chatbot))->assertRedirect(route('login'));
});

test('access is denied when the chatbots menu is disabled', function () {
    disableMenu('chatbots');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('chatbots.index'))->assertForbidden();
});

test('index only shows the authenticated users own chatbots', function () {
    $user = User::factory()->create();
    $own = Chatbot::factory()->create(['user_id' => $user->id]);
    Chatbot::factory()->create();

    $response = $this->actingAs($user)->get(route('chatbots.index'));

    $response->assertOk();
    expect($response->viewData('chatbots')->pluck('id'))->toEqual(collect([$own->id]));
});

test('a user can create a chatbot they own', function () {
    $user = User::factory()->create();
    $aimodel = AImodel::factory()->create();

    $response = $this->actingAs($user)->post(route('chatbots.store'), [
        'title' => 'My Assistant',
        'ai_model_id' => $aimodel->id,
        'api_key' => 'sk-test-key',
    ]);

    $chatbot = Chatbot::first();
    $response->assertRedirect(route('chatbots.show', $chatbot));
    expect($chatbot->user_id)->toBe($user->id);
});

test('creating a chatbot without an api key fails validation instead of crashing', function () {
    $user = User::factory()->create();
    $aimodel = AImodel::factory()->create();

    $this->actingAs($user)->post(route('chatbots.store'), [
        'title' => 'My Assistant',
        'ai_model_id' => $aimodel->id,
    ])->assertSessionHasErrors('api_key');

    expect(Chatbot::count())->toBe(0);
});

test('a user cannot view or delete another users chatbot', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $chatbot = Chatbot::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->get(route('chatbots.show', $chatbot))->assertForbidden();
    $this->actingAs($other)->delete(route('chatbots.destroy', $chatbot))->assertForbidden();
    $this->assertModelExists($chatbot);
});

test('the owner can view and delete their own chatbot', function () {
    $owner = User::factory()->create();
    $chatbot = Chatbot::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get(route('chatbots.show', $chatbot))->assertOk();
    $this->actingAs($owner)->delete(route('chatbots.destroy', $chatbot))->assertRedirect(route('chatbots.index'));
    $this->assertModelMissing($chatbot);
});
