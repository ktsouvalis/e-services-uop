<?php

use App\Models\PangolinNewtAgent;
use App\Models\User;

beforeEach(function () {
    enableMenu('pangolin');
});

test('an agent can be created, edited, and deleted through the CRUD routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.newt-agents.store'), [
        'name' => 'patra', 'ip' => '10.23.2.60',
    ])->assertRedirect(route('pangolin.index', ['tab' => 'newt-agents']));

    $agent = PangolinNewtAgent::first();
    expect($agent->name)->toBe('patra');
    expect($agent->ip)->toBe('10.23.2.60');

    $this->actingAs($user)->put(route('pangolin.newt-agents.update', $agent), [
        'name' => 'patra-renamed', 'ip' => '10.23.2.60',
    ])->assertRedirect(route('pangolin.index', ['tab' => 'newt-agents']));

    expect($agent->fresh()->name)->toBe('patra-renamed');

    $this->actingAs($user)->delete(route('pangolin.newt-agents.destroy', $agent))
        ->assertRedirect(route('pangolin.index', ['tab' => 'newt-agents']));

    $this->assertDatabaseMissing('pangolin_newt_agents', ['id' => $agent->id]);
});

test('creating an agent requires a valid, unique ip', function () {
    $user = User::factory()->create();
    PangolinNewtAgent::factory()->create(['ip' => '10.23.2.60']);

    $this->actingAs($user)->post(route('pangolin.newt-agents.store'), ['name' => 'x', 'ip' => 'not-an-ip'])
        ->assertSessionHasErrors('ip');

    $this->actingAs($user)->post(route('pangolin.newt-agents.store'), ['name' => 'x', 'ip' => '10.23.2.60'])
        ->assertSessionHasErrors('ip');
});

test('updating an agent to keep its own ip does not trip the unique rule on itself', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create(['ip' => '10.23.2.60']);

    $this->actingAs($user)->put(route('pangolin.newt-agents.update', $agent), [
        'name' => 'renamed', 'ip' => '10.23.2.60',
    ])->assertSessionDoesntHaveErrors();
});

test('guests cannot reach any newt-agent route, and a disabled menu forbids it for an authenticated user', function () {
    $agent = PangolinNewtAgent::factory()->create();

    $this->get(route('pangolin.newt-agents.create'))->assertRedirect(route('login'));

    disableMenu('pangolin');
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('pangolin.newt-agents.create'))->assertForbidden();
    $this->actingAs($user)->delete(route('pangolin.newt-agents.destroy', $agent))->assertForbidden();
});
