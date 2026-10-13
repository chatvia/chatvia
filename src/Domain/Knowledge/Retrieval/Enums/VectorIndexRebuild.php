<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Enums;

/**
 * When `vector:reindex` rebuilds a vector index on chunks.
 */
enum VectorIndexRebuild: string
{
    /** Only when the layout must change or the rows doubled since the last build. */
    case WhenGrown = 'when_grown';

    /** Always (the `--force` option). */
    case Always = 'always';
}
