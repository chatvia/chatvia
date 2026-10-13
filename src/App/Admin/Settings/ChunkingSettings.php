<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * How text is split into chunks by the built-in splitter: the unit of the
 * chunk size (tokens or characters), the chunk size and overlap in that
 * unit, the strategy for each kind of content
 * (Domain\Knowledge\Ingestion\Enums\ContentKind) and the tokenizer. A blank
 * value keeps the default from config.
 */
class ChunkingSettings extends AdminSettings
{
    #[ConfigKey('chatvia.ai.chunking.unit')]
    public ?string $chunk_unit = null;

    #[ConfigKey('chatvia.ai.chunking.size')]
    public ?int $chunk_size = null;

    #[ConfigKey('chatvia.ai.chunking.overlap')]
    public ?int $chunk_overlap = null;

    #[ConfigKey('chatvia.ai.chunking.strategies.markdown')]
    public ?string $markdown_strategy = null;

    #[ConfigKey('chatvia.ai.chunking.strategies.text')]
    public ?string $text_strategy = null;

    #[ConfigKey('chatvia.ai.chunking.strategies.transcript')]
    public ?string $transcript_strategy = null;

    #[ConfigKey('chatvia.ai.tokenizer')]
    public ?string $tokenizer = null;

    public static function group(): string
    {
        return 'chunking';
    }
}
