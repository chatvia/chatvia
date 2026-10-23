<?php

use App\Identity\Workspace\Models\User;

/**
 * Agent::enabledLanguages() — the single source of truth for the owner's curated
 * UI languages. Empty selection ⇒ all platform languages (minus the 'auto'
 * sentinel); a subset ⇒ the intersection, in platform order.
 */
beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
});

it('returns every platform language minus auto when nothing is selected', function (): void {
    $all = config('chatvia.languages');
    unset($all['auto']);

    expect($this->agent->enabledLanguages())->toBe($all);
});

it('returns only the selected languages, in platform order', function (): void {
    $this->agent->setSetting('enabled_languages', ['fr', 'de'], 'appearance');

    expect($this->agent->fresh()->enabledLanguages())->toBe([
        'de' => config('chatvia.languages.de'),
        'fr' => config('chatvia.languages.fr'),
    ]);
});

it('ignores unknown or non-string codes', function (): void {
    $this->agent->setSetting('enabled_languages', ['de', 'xx', 123], 'appearance');

    expect(array_keys($this->agent->fresh()->enabledLanguages()))->toBe(['de']);
});
