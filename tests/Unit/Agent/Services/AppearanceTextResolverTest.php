<?php

use Domain\Agent\Services\AppearanceTextResolver;

/**
 * AppearanceTextResolver picks the right copy for a visitor language:
 * manual override > AI translation > base value.
 */
function appearanceFixture(): Illuminate\Support\Collection
{
    return collect([
        'chat_window_title' => 'Chat',
        'welcome_message' => 'How can I help?',
        'composer_placeholder' => 'Ask…',
        'text_translations' => [
            'welcome_message' => ['de' => 'Wie kann ich helfen?'],
            'chat_window_title' => ['de' => 'Chat'],
        ],
        'text_overrides' => [
            'welcome_message' => ['de' => 'Servus, wie kann ich helfen?'],
        ],
    ]);
}

it('prefers a manual override over the translation and base', function (): void {
    $resolved = (new AppearanceTextResolver)->resolve(appearanceFixture(), 'de');

    expect($resolved['welcome_message'])->toBe('Servus, wie kann ich helfen?');
});

it('uses the AI translation when there is no override', function (): void {
    $resolved = (new AppearanceTextResolver)->resolve(appearanceFixture(), 'de');

    expect($resolved['chat_window_title'])->toBe('Chat');
});

it('falls back to the base when the locale has no translation', function (): void {
    $resolved = (new AppearanceTextResolver)->resolve(appearanceFixture(), 'fr');

    expect($resolved['welcome_message'])->toBe('How can I help?')
        ->and($resolved['composer_placeholder'])->toBe('Ask…');
});

it('returns the base text for the auto or null locale', function (): void {
    $resolver = new AppearanceTextResolver;

    expect($resolver->resolve(appearanceFixture(), 'auto')['welcome_message'])->toBe('How can I help?')
        ->and($resolver->resolve(appearanceFixture(), null)['welcome_message'])->toBe('How can I help?');
});

it('normalizes a regional locale to its language', function (): void {
    $resolved = (new AppearanceTextResolver)->resolve(appearanceFixture(), 'de-DE');

    expect($resolved['welcome_message'])->toBe('Servus, wie kann ich helfen?');
});

it('localize() overlays the resolved strings onto the appearance collection', function (): void {
    $localized = (new AppearanceTextResolver)->localize(appearanceFixture(), 'auto', 'de-DE,de;q=0.9');

    // The three text keys are localized…
    expect($localized->get('welcome_message'))->toBe('Servus, wie kann ich helfen?')
        // …and the rest of the appearance payload is left intact.
        ->and($localized->get('composer_placeholder'))->toBe('Ask…')
        ->and($localized->get('text_translations'))->toBeArray();
});

it('localize() prefers a pinned configured language over Accept-Language', function (): void {
    // configuredLanguage 'fr' has no translation, so it must fall back to base —
    // proving the German Accept-Language was ignored.
    $localized = (new AppearanceTextResolver)->localize(appearanceFixture(), 'fr', 'de-DE');

    expect($localized->get('welcome_message'))->toBe('How can I help?');
});
