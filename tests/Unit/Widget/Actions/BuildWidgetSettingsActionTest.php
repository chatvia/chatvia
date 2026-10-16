<?php

use App\Identity\Workspace\Models\User;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Services\DocumentLlm;
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

it('builds the powered-by credit', function (): void {
    config(['chatvia.powered_by' => true]);

    $settings = app(BuildWidgetSettingsAction::class)->execute(createAgent(User::factory()->create()));

    expect($settings->branding->enabled)->toBeTrue()
        ->and($settings->branding->name)->toBe('Powered by Chatvia')
        ->and($settings->branding->url)->toBe('https://chatvia.ai')
        ->and($settings->chatvia_branding)->toBeTrue();
});

it('shows the powered-by credit even when an agent saved it hidden', function (): void {
    config(['chatvia.powered_by' => true]);

    // Agents cannot hide the credit, whatever their settings hold.
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('show_branding', false, 'appearance');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->branding->enabled)->toBeTrue()
        ->and($settings->chatvia_branding)->toBeTrue();
});

it('hides the powered-by credit when the install turns it off', function (): void {
    config(['chatvia.powered_by' => false]);

    $settings = app(BuildWidgetSettingsAction::class)->execute(createAgent(User::factory()->create()));

    expect($settings->branding->enabled)->toBeFalse()
        ->and($settings->chatvia_branding)->toBeFalse();
});

it('resolves bubbles for the visitor language, an override winning over the translation', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('bubbles', [
        ['text' => 'Hi', 'translations' => ['de' => 'Hallo', 'fr' => 'Bonjour'], 'overrides' => ['fr' => 'Salut']],
    ], 'widget');

    $action = app(BuildWidgetSettingsAction::class);

    expect($action->execute($agent->fresh(), 'de-DE')->bubbles)->toBe(['Hallo'])
        ->and($action->execute($agent->fresh(), 'FR')->bubbles)->toBe(['Salut'])
        ->and($action->execute($agent->fresh(), 'es')->bubbles)->toBe(['Hi'])
        ->and($action->execute($agent->fresh(), 'auto')->bubbles)->toBe(['Hi']);
});

it('lets a pinned widget language win over the visitor language', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('language', 'de', 'widget');
    $agent->setSetting('bubbles', [['text' => 'Hi', 'translations' => ['de' => 'Hallo'], 'overrides' => []]], 'widget');
    $agent->setSetting('welcome_message', 'Hello', 'appearance');
    $agent->setSetting('text_translations', ['welcome_message' => ['de' => 'Willkommen']], 'appearance');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh(), 'fr');

    expect($settings->bubbles)->toBe(['Hallo'])
        ->and($settings->welcome_message)->toBe('Willkommen');
});

it('drops an empty bubble from both parallel lists so their indexes stay aligned', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('bubbles', [
        ['text' => '', 'translations' => ['de' => 'Nur Deutsch'], 'overrides' => []],
        ['text' => 'Second', 'translations' => ['de' => 'Zweite'], 'overrides' => []],
    ], 'widget');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->bubbles)->toBe(['Second'])
        ->and($settings->bubble_translations)->toBe([['de' => 'Zweite']]);
});

it('leaves a locale out of a text map when its stored override is empty', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('chat_window_title', 'Support', 'appearance');
    $agent->setSetting('text_translations', ['chat_window_title' => ['de' => 'Hilfe', 'fr' => 'Aide']], 'appearance');
    $agent->setSetting('text_overrides', ['chat_window_title' => ['fr' => '']], 'appearance');

    $settings = app(BuildWidgetSettingsAction::class)->execute($agent->fresh());

    expect($settings->text_translations)->toBe(['title' => ['de' => 'Hilfe']]);
});

it('sends the public Reverb address so a widget build needs none of its own', function (): void {
    config([
        'broadcasting.connections.reverb.key' => 'reverb-app-key',
        'chatvia.widget.reverb' => ['host' => 'ws.example.test', 'port' => '6001', 'scheme' => 'https'],
    ]);

    $settings = app(BuildWidgetSettingsAction::class)->execute(createAgent(User::factory()->create()));

    expect($settings->reverb->toArray())->toBe([
        'key' => 'reverb-app-key',
        'host' => 'ws.example.test',
        'port' => 6001,
        'scheme' => 'https',
    ]);
});

it('leaves the Reverb address empty so the widget uses its API server', function (): void {
    config([
        'broadcasting.connections.reverb.key' => 'reverb-app-key',
        'chatvia.widget.reverb' => ['host' => null, 'port' => '', 'scheme' => null],
    ]);

    $settings = app(BuildWidgetSettingsAction::class)->execute(createAgent(User::factory()->create()));

    expect($settings->reverb->toArray())->toBe([
        'key' => 'reverb-app-key',
        'host' => null,
        'port' => null,
        'scheme' => null,
    ]);
});

it('hides the widget upload button when no LLM can read PDF documents', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->setSetting('allow_file_uploads', true, 'appearance');

    app()->instance(DocumentLlm::class, new DocumentLlm(null));
    expect(app(BuildWidgetSettingsAction::class)->execute($agent->fresh())->allow_file_uploads)->toBeFalse();

    app()->instance(DocumentLlm::class, new DocumentLlm(LlmProviderEnum::MISTRAL));
    expect(app(BuildWidgetSettingsAction::class)->execute($agent->fresh())->allow_file_uploads)->toBeTrue();
});
