<?php

use Domain\Agent\Services\AppearanceTextResolver;
use Illuminate\Support\Collection;

/**
 * AppearanceTextResolver resolves each appearance text for the visitor's
 * locale (the owner's override, else the AI translation, else the base text)
 * and overlays the result on the appearance payload.
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

it('resolves each text by override, then translation, then base for the visitor locale', function (): void {
    $localized = (new AppearanceTextResolver)->localize(atrAppearance(), 'auto', 'de-DE,de;q=0.9');

    expect($localized->get('welcome_message'))->toBe('Servus, wie kann ich helfen?')
        ->and($localized->get('chat_window_title'))->toBe('Unterhaltung')
        ->and($localized->get('composer_placeholder'))->toBe('Ask…')
        ->and($localized->get('text_translations'))->toBeArray();
});
