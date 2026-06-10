<?php

use Domain\Channel\Services\PlainTextFormatter;

/**
 * The formatter strips the model's CommonMark to readable plain text — the
 * plain-text channels (Messenger, Instagram) render no formatting at all, so
 * markers must disappear (never show raw `**asterisks**`), lists keep their
 * bullets/numbers, and link URLs survive so the platform's auto-linking makes
 * them tappable.
 */
beforeEach(fn () => $this->fmt = new PlainTextFormatter);

it('strips bold, bullets, and headings to plain text', function (): void {
    $text = $this->fmt->toPlain("# Weather\n\nHere's **Baghdad**:\n\n- **Temp:** 39°C\n- **Wind:** 1.7 m/s");

    expect($text)->toContain('Weather')
        ->toContain("Here's Baghdad:")
        ->toContain('• Temp: 39°C')
        ->toContain('• Wind: 1.7 m/s')
        ->not->toContain('**')
        ->not->toContain('#')
        ->not->toContain('<');
});

it('numbers ordered lists', function (): void {
    $text = $this->fmt->toPlain("1. first\n2. second");

    expect($text)->toContain('1. first')->toContain('2. second');
});

it('keeps link URLs so the platform can auto-link them', function (): void {
    $text = $this->fmt->toPlain('See [the docs](https://example.com/docs) for more.');

    expect($text)->toContain('the docs (https://example.com/docs)');
});

it('does not duplicate bare-URL links', function (): void {
    $text = $this->fmt->toPlain('Visit https://example.com today.');

    expect($text)->toContain('Visit https://example.com today.')
        ->not->toContain('https://example.com (https://example.com)');
});

it('keeps code content verbatim', function (): void {
    $text = $this->fmt->toPlain("Run `php artisan` then:\n\n```\necho 1;\n```");

    expect($text)->toContain('php artisan')->toContain('echo 1;')->not->toContain('```');
});

it('preserves emoji, accents, and special characters', function (): void {
    $text = $this->fmt->toPlain('Stay cool 😊 — café & 2 < 3');

    expect($text)->toContain('😊')->toContain('café')->toContain('& 2 < 3');
});

it('drops non-http links but keeps their text', function (): void {
    $text = $this->fmt->toPlain('[click](javascript:alert(1))');

    expect($text)->toContain('click')->not->toContain('javascript');
});
