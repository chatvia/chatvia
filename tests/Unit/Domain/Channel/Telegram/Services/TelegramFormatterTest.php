<?php

use Domain\Channel\Telegram\Services\TelegramFormatter;

/**
 * The formatter turns the model's CommonMark into Telegram-safe HTML. It must
 * emit ONLY Telegram-supported tags (never <p>/<ul>/<li>/<strong>, which 400 the
 * send), escape <, >, &, and preserve multibyte text (emoji).
 */
beforeEach(fn (): TelegramFormatter => $this->fmt = new TelegramFormatter);

it('converts bold, bullets, and headings to the Telegram subset', function (): void {
    $html = $this->fmt->toHtml("# Weather\n\nHere's **Baghdad**:\n\n- **Temp:** 39°C\n- **Wind:** 1.7 m/s");

    expect($html)->toContain('<b>Weather</b>')
        ->toContain('<b>Baghdad</b>')
        ->toContain('• <b>Temp:</b> 39°C')
        ->toContain('• <b>Wind:</b> 1.7 m/s')
        // none of the tags Telegram rejects
        ->not->toContain('<p>')
        ->not->toContain('<ul>')
        ->not->toContain('<li>')
        ->not->toContain('<strong>');
});

it('numbers ordered lists', function (): void {
    $html = $this->fmt->toHtml("1. first\n2. second");

    expect($html)->toContain('1. first')->toContain('2. second');
});

it('renders links, inline code and code blocks', function (): void {
    $html = $this->fmt->toHtml("See [docs](https://example.com) and run `php artisan`.\n\n```\necho 1;\n```");

    expect($html)->toContain('<a href="https://example.com">docs</a>')
        ->toContain('<code>php artisan</code>')
        ->toContain('<pre>echo 1;')
        ->toContain('</pre>');
});

it('escapes HTML-special characters in text', function (): void {
    $html = $this->fmt->toHtml('compare 2 < 3 & 5 > 4');

    expect($html)->toContain('2 &lt; 3 &amp; 5 &gt; 4')
        ->not->toContain('2 < 3');
});

it('preserves emoji and accented characters', function (): void {
    $html = $this->fmt->toHtml('Stay cool 😊 — café');

    expect($html)->toContain('😊')->toContain('café');
});

it('drops non-http links but keeps their text', function (): void {
    $html = $this->fmt->toHtml('[click](javascript:alert(1))');

    expect($html)->toContain('click')->not->toContain('javascript');
});

it('strips to clean plain text as a fallback', function (): void {
    $plain = $this->fmt->toPlain("**Baghdad**\n\n- **Temp:** 39°C");

    expect($plain)->toContain('Baghdad')
        ->toContain('• Temp: 39°C')
        ->not->toContain('**')
        ->not->toContain('<b>');
});
