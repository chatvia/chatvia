<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * One prompt the app sends to an AI model, as Settings → Prompts shows it:
 * its name (its file in resources/prompts, such as
 * "knowledge/rewrite-query"), the group it is listed under, a label, what it
 * is for, and the default text (the file).
 */
final class PromptData extends Data
{
    public function __construct(
        public string $name,
        public string $group,
        public string $label,
        public string $description,
        public string $default,
    ) {}
}
