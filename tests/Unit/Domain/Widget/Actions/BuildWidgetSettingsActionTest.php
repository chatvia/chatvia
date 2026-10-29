<?php

use App\Identity\Workspace\Models\User;
use Domain\Widget\Actions\BuildWidgetSettingsAction;

/**
 * The powered-by credit is install-wide: an agent cannot hide it through its
 * own settings, only the install (a Pro license key) can turn it off.
 */
it('shows the powered-by credit even when an agent saved it hidden', function (): void {
    config(['chatvia.powered_by' => true]);
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('show_branding', false, 'appearance');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->branding->enabled)->toBeTrue()
        ->and($settings->chatvia_branding)->toBeTrue();
});
