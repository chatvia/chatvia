<?php

use App\Identity\Workspace\Enums\OnboardingTourStage;
use App\Identity\Workspace\Models\User;

/**
 * Saving how far the person got in the dashboard tour (OnboardingTourController):
 * each stage is kept, null starts the tour again, an unknown stage is refused,
 * and the route is gone while the admin has the tour off.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('saves the stage the person reached', function (string $stage): void {
    $this->actingAs($this->user)
        ->patchJson(route('account.onboarding-tour.update'), ['stage' => $stage])
        ->assertNoContent();

    expect($this->user->fresh()->onboarding_tour)->toBe(OnboardingTourStage::from($stage));
})->with(['agent', 'done']);

it('starts the tour again with a null stage', function (): void {
    $this->user->update(['onboarding_tour' => OnboardingTourStage::Done]);

    $this->actingAs($this->user)
        ->patchJson(route('account.onboarding-tour.update'), ['stage' => null])
        ->assertNoContent();

    expect($this->user->fresh()->onboarding_tour)->toBeNull();
});

it('refuses an unknown or missing stage', function (array $body): void {
    $this->actingAs($this->user)
        ->patchJson(route('account.onboarding-tour.update'), $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stage');

    expect($this->user->fresh()->onboarding_tour)->toBeNull();
})->with([
    'unknown' => [['stage' => 'halfway']],
    'missing' => [[]],
]);

it('answers 404 while the admin has the tour off', function (): void {
    config(['chatvia.onboarding.tour' => false]);

    $this->actingAs($this->user)
        ->patchJson(route('account.onboarding-tour.update'), ['stage' => 'done'])
        ->assertNotFound();

    expect($this->user->fresh()->onboarding_tour)->toBeNull();
});
