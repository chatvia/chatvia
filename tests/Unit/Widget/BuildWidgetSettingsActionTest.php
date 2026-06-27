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

it('marks both embed surfaces enabled by default', function (): void {
    $agent = createAgent(User::factory()->create());

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent);

    expect($settings->widget_enabled)->toBeTrue()
        ->and($settings->bar_enabled)->toBeTrue();
});

it('reflects a disabled widget surface in the payload while the bar stays on', function (): void {
    $agent = createAgent(User::factory()->create());

    $agent->setSetting(key: 'widget', value: false, group: 'surfaces');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->widget_enabled)->toBeFalse()
        ->and($settings->bar_enabled)->toBeTrue();
});

it('exposes per-locale text maps so the widget can localize client-side', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('welcome_message', 'Hello', 'appearance');
    $agent->setSetting('text_translations', ['welcome_message' => ['de' => 'Hallo']], 'appearance');
    $agent->setSetting('text_overrides', ['welcome_message' => ['fr' => 'Salut']], 'appearance');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->text_translations['welcome_message']['de'] ?? null)->toBe('Hallo')
        // A manual override wins over the AI translation in the exported map.
        ->and($settings->text_translations['welcome_message']['fr'] ?? null)->toBe('Salut');
});

it('ships each starter with its per-locale translation map', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->starterSet()->create(['starters' => [
        ['id' => 's1', 'text' => 'Hello?', 'icon' => null, 'translations' => ['de' => 'Hallo?'], 'overrides' => []],
    ]]);

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->starters[0]['text'])->toBe('Hello?')
        ->and($settings->starters[0]['translations']['de'] ?? null)->toBe('Hallo?');
});

it('ships per-bubble translation maps parallel to the resolved bubble text', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('bubbles', [
        ['text' => 'Hi there!', 'translations' => ['de' => 'Hallo!'], 'overrides' => ['fr' => 'Salut !']],
    ], 'widget');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->bubbles[0])->toBe('Hi there!')
        ->and($settings->bubble_translations[0]['de'] ?? null)->toBe('Hallo!')
        ->and($settings->bubble_translations[0]['fr'] ?? null)->toBe('Salut !');
});

it('keeps the legacy plain-string bubble list working', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('bubbles', ['Just a string'], 'widget');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->bubbles[0])->toBe('Just a string')
        ->and($settings->bubble_translations[0])->toBe([]);
});
