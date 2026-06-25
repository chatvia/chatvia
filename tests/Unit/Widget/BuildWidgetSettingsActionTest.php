<?php

use App\Identity\Workspace\Models\User;
use Domain\Widget\Actions\BuildWidgetSettingsAction;

/**
 * BuildWidgetSettingsAction assembles the boot payload the embedded widget
 * reads. These cover the customer-configurable privacy-policy link — the
 * widget shows a consent notice only when the owner has set a URL.
 */
it('surfaces the privacy policy url the owner set on the appearance group', function (): void {
    $agent = createAgent(User::factory()->create());

    $agent->setSetting(key: 'privacy_policy_url', value: 'https://acme.test/privacy', group: 'appearance');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->privacy_policy_url)->toBe('https://acme.test/privacy');
});

it('leaves the privacy policy url null when the owner has not set one', function (): void {
    $agent = createAgent(User::factory()->create());

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent);

    expect($settings->privacy_policy_url)->toBeNull();
});
