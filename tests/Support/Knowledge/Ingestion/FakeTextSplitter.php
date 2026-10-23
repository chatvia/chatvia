<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Ingestion;

use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;

/**
 * Simple stand-in for the text splitter, bound by default in TestCase: one
 * chunk per paragraph, in reading order, and the kind of content of every
 * call, so ingestion tests do not depend on the splitter's settings. A test
 * of the splitter itself, or one that needs other chunks, uses its own.
 */
class FakeTextSplitter implements TextSplitter
{
    /** @var list<ContentKind> */
    public array $kinds = [];

    public function split(string $text, ContentKind $kind): array
    {
        $this->kinds[] = $kind;

        return array_values(array_filter(
            array_map(trim(...), preg_split('/\n\s*\n/', $text) ?: []),
            static fn (string $part): bool => $part !== '',
        ));
    }

    public function name(): string
    {
        return 'fake';
    }
}
