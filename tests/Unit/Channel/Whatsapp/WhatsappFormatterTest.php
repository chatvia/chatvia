<?php

use Domain\Channel\Whatsapp\Services\WhatsappFormatter;

/**
 * The formatter turns the model's CommonMark into WhatsApp markup. WhatsApp
 * renders *bold* / _italic_ / ~strike~ / backticks natively but NOT
 * CommonMark — `**double asterisks**` show as literal asterisks, headings
 * have no syntax (bold line is the convention), and there is no link markup
 * (URLs auto-link bare).
 */
beforeEach(fn (): WhatsappFormatter => $this->fmt = new WhatsappFormatter);

it('converts bold, bullets, and headings to WhatsApp markup', function (): void {
    $text = $this->fmt->toWhatsapp("# Weather\n\nHere's **Baghdad**:\n\n- **Temp:** 39°C\n- **Wind:** 1.7 m/s");

    expect($text)->toContain('*Weather*')
        ->toContain('*Baghdad*')
        ->toContain('- *Temp:* 39°C')
        ->toContain('- *Wind:* 1.7 m/s')
        // double-asterisk bold renders as literal asterisks in WhatsApp
        ->not->toContain('**')
        ->not->toContain('#');
});

it('numbers ordered lists', function (): void {
    $text = $this->fmt->toWhatsapp("1. first\n2. second");

    expect($text)->toContain('1. first')->toContain('2. second');
});

it('renders italics and strikethrough with WhatsApp markers', function (): void {
    $text = $this->fmt->toWhatsapp('this is *italic* and ~~gone~~');

    expect($text)->toContain('_italic_')->toContain('~gone~');
});

it('renders inline code and code blocks, dropping the language tag', function (): void {
    $text = $this->fmt->toWhatsapp("Run `php artisan` then:\n\n```php\necho 1;\n```");

    expect($text)->toContain('`php artisan`')
        ->toContain("```\necho 1;\n```")
        // the fence's language tag would render as literal text in the block
        ->not->toContain('```php');
});

it('keeps link URLs bare so WhatsApp auto-links them', function (): void {
    $text = $this->fmt->toWhatsapp('See [the docs](https://example.com/docs) for more.');

    expect($text)->toContain('the docs (https://example.com/docs)');
});

it('does not duplicate bare-URL links', function (): void {
    $text = $this->fmt->toWhatsapp('Visit https://example.com today.');

    expect($text)->toContain('Visit https://example.com today.')
        ->not->toContain('https://example.com (https://example.com)');
});

it('prefixes quote lines', function (): void {
    $text = $this->fmt->toWhatsapp('> wise words');

    expect($text)->toContain('> wise words');
});

it('preserves emoji, accents, and special characters', function (): void {
    $text = $this->fmt->toWhatsapp('Stay cool 😊 — café & 2 < 3');

    expect($text)->toContain('😊')->toContain('café')->toContain('& 2 < 3');
});

it('drops non-http links but keeps their text', function (): void {
    $text = $this->fmt->toWhatsapp('[click](javascript:alert(1))');

    expect($text)->toContain('click')->not->toContain('javascript');
});
