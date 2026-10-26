<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\UpdateOnboardingTourRequest;
use App\Identity\Workspace\Enums\OnboardingTourStage;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\Response;

/**
 * Saves how far the signed-in person got in the dashboard tour, so it picks
 * up on the next page and never comes back once done or skipped. Answers
 * 404 while the admin has the tour off (Settings → Workspaces → Onboarding).
 */
class OnboardingTourController
{
    /**
     * A single-column write with no rule or event, so it stays inline here
     * rather than behind an Action.
     */
    public function update(UpdateOnboardingTourRequest $request): Response
    {
        abort_unless((bool) (config('chatvia.onboarding.tour') ?? true), 404);

        /** @var User $user */
        $user = $request->user();

        $user->update(['onboarding_tour' => $request->enum('stage', OnboardingTourStage::class)]);

        return response()->noContent();
    }
}
