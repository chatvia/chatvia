<?php

declare(strict_types=1);

use Domain\Agent\Actions\Settings\UpdateAgentMediaModelChoiceAction;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Enums\GeneratedMediaKind;

/** The agent's switch for the image or video model choice, saved per kind. */
it('saves the switch for one kind', function (): void {
    $agent = Agent::factory()->create();

    app(UpdateAgentMediaModelChoiceAction::class)->execute($agent, GeneratedMediaKind::Video, true);

    $settings = $agent->fresh()->settings(OfferedMediaModels::SETTINGS_GROUP);

    expect($settings->get('video'))->toBeTrue()
        ->and($settings->get('image'))->toBeFalse();
});
