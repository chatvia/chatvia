<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Support\Prompt\PromptTemplate;

/**
 * The prompts an admin rewrote (Settings → Prompts), as prompt name
 * ("knowledge/rewrite-query") → text. A prompt without an entry uses its file
 * in resources/prompts, which stays the default. An edit may only use the
 * safe part of Blade ({@see PromptTemplate}); prompt() renders the file
 * instead of an edit that is not.
 */
class PromptSettings extends AdminSettings
{
    /** @var array<string, string>|null */
    #[ConfigKey('chatvia.prompts')]
    public ?array $overrides = null;

    public static function group(): string
    {
        return 'prompts';
    }
}
