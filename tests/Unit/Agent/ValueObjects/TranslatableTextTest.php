<?php

use Domain\Agent\ValueObjects\TranslatableText;

/**
 * One resolution rule for every piece of translatable copy: the owner's
 * manual edit, else the AI translation, else the base text.
 */
it('prefers the manual edit, then the AI translation, then the base text', function (): void {
    $text = new TranslatableText('Hello', ['de' => 'Hallo', 'fr' => 'Bonjour'], ['de' => 'Servus']);

    expect($text->in('de'))->toBe('Servus')
        ->and($text->in('fr'))->toBe('Bonjour')
        ->and($text->in('es'))->toBe('Hello');
});

it('normalizes a regional or upper-case locale and ignores auto', function (?string $locale, string $expected): void {
    $text = new TranslatableText('Hello', ['de' => 'Hallo']);

    expect($text->in($locale))->toBe($expected);
})->with([
    ['de-AT', 'Hallo'],
    ['DE', 'Hallo'],
    ['auto', 'Hello'],
    ['*', 'Hello'],
    ['', 'Hello'],
    [null, 'Hello'],
]);

it('reads the stored shape and the legacy plain string', function (): void {
    expect(TranslatableText::fromStored('Plain')->in('de'))->toBe('Plain')
        ->and(TranslatableText::fromStored(['text' => 'Hi', 'translations' => ['de' => 'Hallo'], 'overrides' => 'junk'])->overrides)->toBe([])
        ->and(TranslatableText::fromStored(['text' => 'Hi', 'translations' => ['de' => 'Hallo']])->in('de'))->toBe('Hallo');
});

it('knows when every enabled language is already translated', function (): void {
    $text = new TranslatableText('Hello', ['de' => 'Hallo', 'fr' => '']);

    expect($text->isTranslatedInto(['de']))->toBeTrue()
        ->and($text->isTranslatedInto(['de', 'fr']))->toBeFalse()
        ->and(new TranslatableText('Hello')->isTranslatedInto(['de']))->toBeFalse();
});

it('keeps only non-empty edits for enabled languages', function (): void {
    expect(TranslatableText::onlyLanguages(['de' => ' Servus ', 'fr' => '', 'xx' => 'Nope', 'es' => 5], ['de' => 'German', 'fr' => 'French', 'es' => 'Spanish']))
        ->toBe(['de' => 'Servus']);
});
