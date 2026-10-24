<?php

use Domain\Channel\Services\PlainTextFormatter;

/**
 * The formatter strips the model's CommonMark to readable plain text: the
 * plain-text channels (Messenger, Instagram) render no formatting at all, so
 * markers must disappear (never show raw `**asterisks**`) while bullets and
 * code stay readable. Lists, links and multibyte text are covered for every
 * formatter in MarkdownDomFormatterTest.
 */
beforeEach(fn (): PlainTextFormatter => $this->fmt = new PlainTextFormatter);

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

it('keeps code content verbatim', function (): void {
    $text = $this->fmt->toPlain("Run `php artisan` then:\n\n```\necho 1;\n```");

    expect($text)->toContain('php artisan')->toContain('echo 1;')->not->toContain('```');
});
