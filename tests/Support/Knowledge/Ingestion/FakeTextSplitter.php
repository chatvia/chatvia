<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Ingestion;

use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;

/**
 * Simple stand-in for the text splitter, bound by default in TestCase: one
 * chunk per paragraph, in reading order, so ingestion tests do not depend on
 * the splitter's settings. {@see returning()} gives every text the same
 * chunks instead. It keeps the text and the kind of content of every call. A
 * test of the splitter itself uses the real one.
 */
class FakeTextSplitter implements TextSplitter
{
    /** @var list<ContentKind> */
    public array $kinds = [];

    /** @var list<string> */
    public array $texts = [];

    /**
     * @param  list<string>|null  $chunks  the chunks of every text; null to split at paragraph breaks
     */
    public function __construct(private readonly ?array $chunks = null) {}

    /** A splitter that splits every text into these chunks. */
    public static function returning(string ...$chunks): self
    {
        return new self(array_values($chunks));
    }

    public function split(string $text, ContentKind $kind): array
    {
        $this->kinds[] = $kind;
        $this->texts[] = $text;

        return $this->chunks ?? array_values(array_filter(
            array_map(trim(...), preg_split('/\n\s*\n/', $text) ?: []),
            static fn (string $part): bool => $part !== '',
        ));
    }

    public function name(): string
    {
        return 'fake';
    }
}
