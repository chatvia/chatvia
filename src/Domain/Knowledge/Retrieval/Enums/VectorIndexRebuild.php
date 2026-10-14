<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Enums;

/**
 * When a vector index on chunks is rebuilt: by `vector:reindex`, or after a
 * re-index of the stored vectors (a new embedding model).
 */
enum VectorIndexRebuild: string
{
    /** Only when the layout must change or the rows doubled since the last build. */
    case WhenGrown = 'when_grown';

    /** Always (the `--force` option). */
    case Always = 'always';

    /**
     * Whenever the index has partitions, or now needs them: they were trained
     * on vectors that were all made again (a new embedding model). A flat
     * index has nothing trained, so it is kept.
     */
    case WhenPartitioned = 'when_partitioned';
}
