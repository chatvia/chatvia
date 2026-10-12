<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * How text files, ebooks and web pages are split into chunks: with the
 * chunker (github.com/chatvia/chunker, hosted at chunk.fit or self-hosted)
 * and its strategy and options, or with the built-in splitter. The strategy
 * options are sent only to the strategies that accept them
 * (Domain\Knowledge\Ingestion\Enums\ChunkerStrategy); a blank one uses
 * Chatvia's default from config, or else the chunker's own.
 */
class ChunkingSettings extends AdminSettings
{
    #[ConfigKey('chatvia.ai.text_splitter')]
    public ?string $text_splitter = null;

    #[ConfigKey('services.chunkfit.base_url')]
    public ?string $chunker_url = null;

    #[ConfigKey('services.chunkfit.api_key'), ShouldBeEncrypted]
    public ?string $chunker_api_key = null;

    #[ConfigKey('services.chunkfit.strategy')]
    public ?string $strategy = null;

    #[ConfigKey('chatvia.ai.tokenizer')]
    public ?string $tokenizer = null;

    #[ConfigKey('services.chunkfit.options.chunk_size')]
    public ?int $chunk_size = null;

    #[ConfigKey('services.chunkfit.options.chunk_overlap')]
    public ?int $chunk_overlap = null;

    #[ConfigKey('services.chunkfit.options.length_unit')]
    public ?string $length_unit = null;

    #[ConfigKey('services.chunkfit.options.language')]
    public ?string $language = null;

    /** @var list<string>|null */
    #[ConfigKey('services.chunkfit.options.separators')]
    public ?array $separators = null;

    #[ConfigKey('services.chunkfit.options.separator')]
    public ?string $separator = null;

    #[ConfigKey('services.chunkfit.options.max_header_level')]
    public ?int $max_header_level = null;

    #[ConfigKey('services.chunkfit.options.strip_headers')]
    public ?bool $strip_headers = null;

    #[ConfigKey('services.chunkfit.options.buffer_size')]
    public ?int $buffer_size = null;

    #[ConfigKey('services.chunkfit.options.breakpoint_percentile_threshold')]
    public ?int $breakpoint_percentile_threshold = null;

    #[ConfigKey('services.chunkfit.options.min_sentences_per_chunk')]
    public ?int $min_sentences_per_chunk = null;

    #[ConfigKey('services.chunkfit.options.min_characters_per_chunk')]
    public ?int $min_characters_per_chunk = null;

    #[ConfigKey('services.chunkfit.options.threshold')]
    public ?float $threshold = null;

    #[ConfigKey('services.chunkfit.options.similarity_window')]
    public ?int $similarity_window = null;

    #[ConfigKey('chatvia.ai.local_splitter.chunk_tokens')]
    public ?int $local_chunk_tokens = null;

    #[ConfigKey('chatvia.ai.local_splitter.overlap_tokens')]
    public ?int $local_overlap_tokens = null;

    public static function group(): string
    {
        return 'chunking';
    }
}
