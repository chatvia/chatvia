<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;

/**
 * Saving a setting is one upsert on the (model, group, key) unique key: a
 * second save of the same key updates the one row in place, and each saved
 * value reads back with its own type.
 */
it('keeps one row per model, group and key', function (): void {
    $agent = Agent::factory()->create();

    $agent->setSetting('chat_window_title', 'First', 'appearance');
    $agent->setSetting('chat_window_title', 'Second', 'appearance');

    $rows = ModelSetting::query()
        ->where('model_type', $agent->getMorphClass())
        ->where('model_id', $agent->id)
        ->where('group', 'appearance')
        ->where('key', 'chat_window_title');

    expect($rows->count())->toBe(1)
        ->and($rows->value('value'))->toBe('Second')
        ->and($agent->settings('appearance')->get('chat_window_title'))->toBe('Second');
});

it('reads saved values back with their type', function (): void {
    $agent = Agent::factory()->create();

    $agent->setSetting('allow_file_uploads', true, 'appearance');
    $agent->setSetting('allow_voice_input', false, 'appearance');
    $agent->setSetting('color_shade', 500, 'appearance');
    $agent->setSetting('enabled_languages', ['en', 'ar'], 'appearance');

    $settings = Agent::query()->findOrFail($agent->id)->settings('appearance');

    expect($settings->get('allow_file_uploads'))->toBeTrue()
        ->and($settings->get('allow_voice_input'))->toBeFalse()
        ->and($settings->get('color_shade'))->toBe(500)
        ->and($settings->get('enabled_languages'))->toBe(['en', 'ar']);
});

it('does not touch another agent\'s setting of the same key', function (): void {
    [$one, $two] = Agent::factory()->count(2)->create()->all();

    $one->setSetting('chat_window_title', 'One', 'appearance');
    $two->setSetting('chat_window_title', 'Two', 'appearance');

    expect(Agent::query()->findOrFail($one->id)->settings('appearance')->get('chat_window_title'))->toBe('One')
        ->and(Agent::query()->findOrFail($two->id)->settings('appearance')->get('chat_window_title'))->toBe('Two');
});
