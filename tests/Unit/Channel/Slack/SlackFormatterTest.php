<?php

use Domain\Channel\Slack\Services\SlackFormatter;

/**
 * The formatter turns the model's CommonMark into Slack mrkdwn. It must emit
 * Slack's own markers (`*bold*`, `_italic_`, `<url|text>`), never CommonMark
 * (`**bold**`) or HTML tags, escape &, <, >, and preserve multibyte text.
 */
beforeEach(fn (): SlackFormatter => $this->fmt = new SlackFormatter);

it('converts bold, bullets, and headings to mrkdwn', function (): void {
    $text = $this->fmt->toMrkdwn("# Weather\n\nHere's **Baghdad**:\n\n- **Temp:** 39°C\n- **Wind:** 1.7 m/s");

    expect($text)->toContain('*Weather*')
        ->toContain('*Baghdad*')
        ->toContain('• *Temp:* 39°C')
        ->toContain('• *Wind:* 1.7 m/s')
        // no CommonMark or HTML leaking through
        ->not->toContain('**')
        ->not->toContain('<p>')
        ->not->toContain('<b>');
});

it('numbers ordered lists', function (): void {
    $text = $this->fmt->toMrkdwn("1. first\n2. second");

    expect($text)->toContain('1. first')->toContain('2. second');
});

it('renders links, inline code and code blocks', function (): void {
    $text = $this->fmt->toMrkdwn("See [docs](https://example.com) and run `php artisan`.\n\n```\necho 1;\n```");

    expect($text)->toContain('<https://example.com|docs>')
        ->toContain('`php artisan`')
        ->toContain("```\necho 1;\n```");
});

it('renders italics and strikethrough', function (): void {
    $text = $this->fmt->toMrkdwn('this is *italic* and ~~gone~~');

    expect($text)->toContain('_italic_')->toContain('~gone~');
});

it('escapes the characters Slack treats as control sequences', function (): void {
    $text = $this->fmt->toMrkdwn('compare 2 < 3 & 5 > 4');

    expect($text)->toContain('2 &lt; 3 &amp; 5 &gt; 4')
        ->not->toContain('2 < 3');
});

it('preserves emoji and accented characters', function (): void {
    $text = $this->fmt->toMrkdwn('Stay cool 😊 — café');

    expect($text)->toContain('😊')->toContain('café');
});

it('drops non-http links but keeps their text', function (): void {
    $text = $this->fmt->toMrkdwn('[click](javascript:alert(1))');

    expect($text)->toContain('click')->not->toContain('javascript');
});

it('prefixes blockquote lines', function (): void {
    $text = $this->fmt->toMrkdwn('> wise words');

    expect($text)->toContain('> wise words');
});
