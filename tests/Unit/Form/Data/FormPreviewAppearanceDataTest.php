<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Data\FormPreviewAppearanceData;

/**
 * The Forms page preview copies the agent's widget look: saved values come
 * through, and blank ones fall back to the agent's name, the default brand
 * colour and the visitor's own light/dark theme. The mic needs speech-to-text.
 */
it('copies the saved widget look', function (): void {
    $agent = Agent::factory()->create(['name' => 'Support Bot']);
    $agent->setSetting('chat_window_title', 'Ask us', 'appearance');
    $agent->setSetting('color_theme', 'blue', 'appearance');
    $agent->setSetting('color_shade', 600, 'appearance');
    $agent->setSetting('theme', 'dark', 'widget');
    $agent->setSetting('composer_placeholder', 'Ask us anything', 'appearance');
    $agent->setSetting('allow_voice_input', true, 'appearance');

    $data = FormPreviewAppearanceData::fromAgent(Agent::query()->findOrFail($agent->id), true);

    expect($data->title)->toBe('Ask us')
        ->and($data->color_theme)->toBe('blue')
        ->and($data->color_shade)->toBe(600)
        ->and($data->theme)->toBe('dark')
        ->and($data->composer_placeholder)->toBe('Ask us anything')
        ->and($data->voice_input)->toBeTrue();
});

it('falls back to the agent name, the default colour and the auto theme', function (): void {
    $agent = Agent::factory()->create(['name' => 'Support Bot']);
    $agent->setSetting('chat_window_title', '', 'appearance');
    $agent->setSetting('color_theme', '', 'appearance');
    $agent->setSetting('theme', 'sepia', 'widget');

    $data = FormPreviewAppearanceData::fromAgent(Agent::query()->findOrFail($agent->id), false);

    expect($data->title)->toBe('Support Bot')
        ->and($data->logo_url)->toBeNull()
        ->and($data->avatar_url)->toBeNull()
        ->and($data->color_theme)->toBe('black')
        ->and($data->theme)->toBe('auto')
        ->and($data->voice_input)->toBeFalse();
});
