<?php

declare(strict_types=1);

use Domain\Llm\Services\TikToken;

/**
 * The built-in tokenizer counts with the encoding set in the admin panel
 * (cl100k_base when it is unknown), or with one given for a single count.
 */
it('counts with the chosen tokenizer, and cl100k_base when it is unknown', function (): void {
    $text = 'Tokenizers split words like antidisestablishmentarianism differently.';

    config(['chatvia.ai.tokenizer' => 'cl100k_base']);
    $cl100k = TikToken::countTokens($text);
    config(['chatvia.ai.tokenizer' => 'r50k_base']);
    $r50k = TikToken::countTokens($text);
    config(['chatvia.ai.tokenizer' => 'not-a-tokenizer']);

    expect($cl100k)->not->toBe($r50k)
        ->and(TikToken::encoding())->toBe('cl100k_base')
        ->and(TikToken::countTokens($text))->toBe($cl100k)
        ->and(TikToken::countTokens($text, 'r50k_base'))->toBe($r50k)
        ->and(TikToken::encoding('o200k_base'))->toBe('o200k_base');
});
