<?php

use Domain\Channel\Services\PlainTextFormatter;
use Domain\Channel\Slack\Services\SlackFormatter;
use Domain\Channel\Telegram\Services\TelegramFormatter;
use Domain\Channel\Whatsapp\Services\WhatsappFormatter;

/**
 * The CommonMark walk every channel formatter shares never passes on a link
 * that is not http(s): a `javascript:` link from the model keeps only its text.
 */
it('drops a link that is not http but keeps its text', function (Closure $format): void {
    expect($format('[click](javascript:alert(1))'))->toContain('click')->not->toContain('javascript');
})->with([
    'plain text' => [fn (string $markdown): string => new PlainTextFormatter()->toPlain($markdown)],
    'Slack' => [fn (string $markdown): string => new SlackFormatter()->toMrkdwn($markdown)],
    'Telegram' => [fn (string $markdown): string => new TelegramFormatter()->toHtml($markdown)],
    'WhatsApp' => [fn (string $markdown): string => new WhatsappFormatter()->toWhatsapp($markdown)],
]);
