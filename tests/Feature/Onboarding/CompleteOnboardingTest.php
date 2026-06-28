<?php

use App\Identity\Workspace\Models\User;

beforeEach(function (): void {
    // Factory users are onboarded by default; start these ones fresh.
    $this->user = User::factory()->create(['onboarded_at' => null]);
});

it('redirects guests to login', function (): void {
    $this->post(route('onboarding.complete'))->assertRedirect(route('login'));
});

it('stamps onboarded_at for the signed-in user', function (): void {
    expect($this->user->onboarded_at)->toBeNull();

    $this->actingAs($this->user)
        ->from(route('dashboard'))
        ->post(route('onboarding.complete'))
        ->assertRedirect(route('dashboard'));

    expect($this->user->fresh()->onboarded_at)->not->toBeNull();
});

it('keeps the original timestamp on repeat calls', function (): void {
    $first = now()->subDay()->startOfSecond();
    $this->user->update(['onboarded_at' => $first]);

    $this->actingAs($this->user)
        ->from(route('dashboard'))
        ->post(route('onboarding.complete'))
        ->assertRedirect();

    expect($this->user->fresh()->onboarded_at->equalTo($first))->toBeTrue();
});
