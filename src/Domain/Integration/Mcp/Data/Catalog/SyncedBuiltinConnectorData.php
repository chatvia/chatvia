<?php

namespace Domain\Integration\Mcp\Data\Catalog;

/**
 * One builtin connector the deploy sync catalogued: its slug, how it is
 * offered (an McpServerCategory value for global builtins, `capability` for
 * per-agent opt-in servers) and how many tools it advertises.
 */
final readonly class SyncedBuiltinConnectorData
{
    public const string CAPABILITY = 'capability';

    public function __construct(
        public string $slug,
        public string $offeredAs,
        public int $toolsCount,
    ) {}
}
