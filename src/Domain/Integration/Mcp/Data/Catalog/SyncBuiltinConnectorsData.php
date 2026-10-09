<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Data\Catalog;

/**
 * Options for the deploy-time builtin catalog sync.
 */
final readonly class SyncBuiltinConnectorsData
{
    public function __construct(
        /** Leave embedding to the queue instead of embedding inline. */
        public bool $queueEmbedding = false,
        /** Re-embed every tool, not only the ones whose text changed. */
        public bool $reembedAll = false,
    ) {}
}
