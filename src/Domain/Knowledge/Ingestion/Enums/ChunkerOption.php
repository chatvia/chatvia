<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Enums;

/**
 * An option of a chunker strategy, by its name in the chunker's API
 * (github.com/chatvia/chunker, POST /v1/chunk). Each strategy accepts only
 * its own options ({@see ChunkerStrategy::options()}); the chunker refuses
 * any other.
 */
enum ChunkerOption: string
{
    case ChunkSize = 'chunk_size';
    case ChunkOverlap = 'chunk_overlap';
    case LengthUnit = 'length_unit';
    case Separators = 'separators';
    case Language = 'language';
    case Separator = 'separator';
    case MaxHeaderLevel = 'max_header_level';
    case StripHeaders = 'strip_headers';
    case BufferSize = 'buffer_size';
    case BreakpointPercentileThreshold = 'breakpoint_percentile_threshold';
    case MinSentencesPerChunk = 'min_sentences_per_chunk';
    case MinCharactersPerChunk = 'min_characters_per_chunk';
    case Threshold = 'threshold';
    case SimilarityWindow = 'similarity_window';

    /** The languages whose code-aware separators the recursive splitter knows. */
    public const array LANGUAGES = [
        'markdown' => 'Markdown',
        'html' => 'HTML',
        'latex' => 'LaTeX',
        'rst' => 'reStructuredText',
        'python' => 'Python',
        'js' => 'JavaScript',
        'ts' => 'TypeScript',
        'php' => 'PHP',
        'java' => 'Java',
        'kotlin' => 'Kotlin',
        'csharp' => 'C#',
        'c' => 'C',
        'cpp' => 'C++',
        'go' => 'Go',
        'rust' => 'Rust',
        'ruby' => 'Ruby',
        'swift' => 'Swift',
        'scala' => 'Scala',
        'elixir' => 'Elixir',
        'haskell' => 'Haskell',
        'lua' => 'Lua',
        'perl' => 'Perl',
        'r' => 'R',
        'sol' => 'Solidity',
        'proto' => 'Protocol Buffers',
        'powershell' => 'PowerShell',
        'cobol' => 'COBOL',
        'visualbasic6' => 'Visual Basic 6',
    ];

    /** The tokenizers (tiktoken encodings) the chunker counts tokens with. */
    public const array ENCODINGS = [
        'cl100k_base' => 'cl100k_base (GPT-4, GPT-3.5)',
        'o200k_base' => 'o200k_base (GPT-4o and newer)',
        'p50k_base' => 'p50k_base (Codex, text-davinci)',
        'r50k_base' => 'r50k_base (GPT-3)',
    ];
}
