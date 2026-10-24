<?php

use Domain\Channel\Services\PlainTextFormatter;
use Domain\Channel\Slack\Services\SlackFormatter;
use Domain\Channel\Telegram\Services\TelegramFormatter;
use Domain\Channel\Whatsapp\Services\WhatsappFormatter;

/**
 * The CommonMark walk every channel formatter shares: ordered lists keep their
 * numbers, a link that is not http(s) keeps only its text, and multibyte text
 * survives. Plain text and WhatsApp also share the default link format
 * ("text (url)", never repeating a bare URL) and leave &, < and > as they
 * are. Each formatter's own markup is in its own test.
 */
dataset('mdfFormatters', [
    'plain text' => [fn (string $markdown): string => new PlainTextFormatter()->toPlain($markdown)],
    'Slack' => [fn (string $markdown): string => new SlackFormatter()->toMrkdwn($markdown)],
    'Telegram' => [fn (string $markdown): string => new TelegramFormatter()->toHtml($markdown)],
    'WhatsApp' => [fn (string $markdown): string => new WhatsappFormatter()->toWhatsapp($markdown)],
]);

dataset('mdfDefaultLinks', [
    'plain text' => [fn (string $markdown): string => new PlainTextFormatter()->toPlain($markdown)],
    'WhatsApp' => [fn (string $markdown): string => new WhatsappFormatter()->toWhatsapp($markdown)],
]);

it('numbers ordered lists', function (Closure $format): void {
    expect($format("1. first\n2. second"))->toContain('1. first')->toContain('2. second');
})->with('mdfFormatters');

it('drops a link that is not http but keeps its text', function (Closure $format): void {
    expect($format('[click](javascript:alert(1))'))->toContain('click')->not->toContain('javascript');
})->with('mdfFormatters');

it('keeps emoji and accented characters', function (Closure $format): void {
    expect($format('Stay cool 😊 — café'))->toContain('😊')->toContain('café');
})->with('mdfFormatters');

it('writes a link as its text followed by its URL, so the platform can link it', function (Closure $format): void {
    expect($format('See [the docs](https://example.com/docs) for more.'))->toContain('the docs (https://example.com/docs)');
})->with('mdfDefaultLinks');

it('does not repeat a bare URL', function (Closure $format): void {
    expect($format('Visit https://example.com today.'))
        ->toContain('Visit https://example.com today.')
        ->not->toContain('https://example.com (https://example.com)');
})->with('mdfDefaultLinks');

it('leaves &, < and > as they are', function (Closure $format): void {
    expect($format('café & 2 < 3'))->toContain('& 2 < 3');
})->with('mdfDefaultLinks');
