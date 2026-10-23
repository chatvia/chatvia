<?php

use Domain\Agent\Services\AppearanceTextResolver;
use Illuminate\Support\Collection;

/**
 * AppearanceTextResolver reads each appearance field's own translations and
 * overrides and hands them to TranslatableText (whose precedence rules have
 * their own test), then overlays the result on the appearance payload.
 */
function atrAppearance(): Collection
{
    return collect([
        'chat_window_title' => 'Chat',
        'welcome_message' => 'How can I help?',
        'composer_placeholder' => 'Ask…',
        'text_translations' => [
            'welcome_message' => ['de' => 'Wie kann ich helfen?'],
            'chat_window_title' => ['de' => 'Unterhaltung'],
        ],
        'text_overrides' => [
            'welcome_message' => ['de' => 'Servus, wie kann ich helfen?'],
        ],
    ]);
}

it('uses the AI translation when there is no override', function (): void {
    $resolved = (new AppearanceTextResolver)->resolve(atrAppearance(), 'de');

    expect($resolved['chat_window_title'])->toBe('Unterhaltung')
        ->and($resolved['welcome_message'])->toBe('Servus, wie kann ich helfen?');
});

it('falls back to the base when the locale has no translation', function (): void {
    $resolved = (new AppearanceTextResolver)->resolve(atrAppearance(), 'fr');

    expect($resolved['welcome_message'])->toBe('How can I help?')
        ->and($resolved['composer_placeholder'])->toBe('Ask…');
});

it('localize() overlays the resolved strings onto the appearance collection', function (): void {
    $localized = (new AppearanceTextResolver)->localize(atrAppearance(), 'auto', 'de-DE,de;q=0.9');

    // The three text keys are localized…
    expect($localized->get('welcome_message'))->toBe('Servus, wie kann ich helfen?')
        // …and the rest of the appearance payload is left intact.
        ->and($localized->get('composer_placeholder'))->toBe('Ask…')
        ->and($localized->get('text_translations'))->toBeArray();
});

it('localize() prefers a pinned configured language over Accept-Language', function (): void {
    // configuredLanguage 'fr' has no translation, so it must fall back to base —
    // proving the German Accept-Language was ignored.
    $localized = (new AppearanceTextResolver)->localize(atrAppearance(), 'fr', 'de-DE');

    expect($localized->get('welcome_message'))->toBe('How can I help?');
});
