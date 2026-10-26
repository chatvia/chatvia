<?php

use App\Identity\Workspace\Enums\OnboardingTourStage;
use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * HandleInertiaRequests shares how far the person got in the dashboard tour,
 * or null while the admin has the tour off (Settings → Workspaces →
 * Onboarding), so the dashboard neither opens it nor offers "Take the tour".
 */
it('shares the person\'s place in the tour', function (?OnboardingTourStage $stage): void {
    $user = User::factory()->create(['onboarding_tour' => $stage]);

    $this->actingAs($user)->get(route('account.profile.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('onboardingTour.stage', $stage?->value));
})->with([
    'not started' => [null],
    'agent part next' => [OnboardingTourStage::Agent],
    'done' => [OnboardingTourStage::Done],
]);

it('shares no tour while the admin has it off', function (): void {
    config(['chatvia.onboarding.tour' => false]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('account.profile.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('onboardingTour', null));
});
