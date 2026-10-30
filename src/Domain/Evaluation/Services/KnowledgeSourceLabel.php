<?php

declare(strict_types=1);

namespace Domain\Evaluation\Services;

use Illuminate\Support\Str;

/**
 * The short label the Evals "From knowledge" picker shows next to a question
 * for the kind of source its chunk came from (a chunk's `chunkable_type`
 * morph alias: `source_file`, `source_website`, `source_text`…).
 */
final class KnowledgeSourceLabel
{
    private const array LABELS = [
        'source_file' => 'File',
        'source_website' => 'Website',
        'source_text' => 'Text',
    ];

    public static function for(string $morphAlias): string
    {
        return self::LABELS[$morphAlias] ?? Str::ucfirst(Str::replace('_', ' ', $morphAlias));
    }
}
