<?php

use App\Models\AImodel;
use App\Models\User;

test('guests are redirected to login rather than reaching AI model routes unauthenticated', function () {
    $aimodel = AImodel::factory()->create();

    $this->get(route('aimodels.index'))->assertRedirect(route('login'));
    $this->post(route('aimodels.store'), ['name' => 'x', 'description' => 'y'])->assertRedirect(route('login'));
    $this->get(route('aimodels.edit', $aimodel))->assertRedirect(route('login'));
    $this->patch(route('aimodels.update', $aimodel), ['name' => 'x'])->assertRedirect(route('login'));
    $this->delete(route('aimodels.destroy', $aimodel))->assertRedirect(route('login'));

    expect(AImodel::count())->toBe(1);
});

test('non-admin authenticated users are forbidden from every AI model route', function () {
    $user = User::factory()->create(['admin' => false]);
    $aimodel = AImodel::factory()->create();

    $this->actingAs($user)->get(route('aimodels.index'))->assertForbidden();
    $this->actingAs($user)->post(route('aimodels.store'), ['name' => 'x', 'description' => 'y'])->assertForbidden();
    $this->actingAs($user)->get(route('aimodels.edit', $aimodel))->assertForbidden();
    $this->actingAs($user)->patch(route('aimodels.update', $aimodel), ['name' => 'x'])->assertForbidden();
    $this->actingAs($user)->delete(route('aimodels.destroy', $aimodel))->assertForbidden();

    expect(AImodel::count())->toBe(1);
});

test('admin can manage AI models', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('aimodels.index'))->assertOk();

    $this->actingAs($admin)->post(route('aimodels.store'), [
        'name' => 'GPT Test',
        'description' => 'A test model',
    ])->assertRedirect(route('aimodels.index'));

    $aimodel = AImodel::firstWhere('name', 'GPT Test');
    expect($aimodel)->not->toBeNull();

    $this->actingAs($admin)->delete(route('aimodels.destroy', $aimodel));
    $this->assertModelMissing($aimodel);
});
