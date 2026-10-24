<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Actions\Catalog\SyncBuiltinConnectorsAction;
use Domain\Integration\Mcp\Data\Catalog\SyncBuiltinConnectorsData;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Services\Routing\BuiltinToolRouter;

/**
 * The owner's "Ask your data" chat has no agent: its tools are routed from
 * the global built-in catalog by category, each with the internal /mcp/
 * address it is called at.
 */
it('routes a message-count question to the message stats tool, with an internal address for every tool', function (): void {
    app(SyncBuiltinConnectorsAction::class)->execute(new SyncBuiltinConnectorsData(queueEmbedding: false));

    $result = app(BuiltinToolRouter::class)->route(
        'how many messages did I get this cycle',
        [McpServerCategory::Analytics, McpServerCategory::Builtin],
        6,
    );
    $names = array_map(static fn (array $definition): string => $definition['function']['name'], $result['definitions']);

    expect($names)->toContain('get-message-stats-tool')
        ->and(array_keys($result['routes']))->toEqualCanonicalizing($names)
        ->and($result['routes'])->each->toContain('/mcp/');
});
