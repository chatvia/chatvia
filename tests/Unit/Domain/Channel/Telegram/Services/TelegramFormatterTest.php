<?php

use Domain\Channel\Telegram\Services\TelegramFormatter;

/**
 * The model's text goes to Telegram as HTML, so <, > and & in it are escaped
 * and can never become markup.
 */
it('escapes HTML-special characters in text', function (): void {
    $html = new TelegramFormatter()->toHtml('compare 2 < 3 & 5 > 4 <script>');

    expect($html)->toContain('2 &lt; 3 &amp; 5 &gt; 4')
        ->not->toContain('<script>');
});
