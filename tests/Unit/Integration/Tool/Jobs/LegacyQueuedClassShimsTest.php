<?php

use Domain\Integration\Mcp\Events\McpToolInvoked;
use Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob;
use Domain\Integration\Tool\Jobs\EmbedFunctionJob;
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;

/**
 * Jobs and queued-listener payloads serialized before the deploy still carry
 * the old class names. The deprecated shims at those names must unserialize
 * into objects the new code accepts, with their data intact.
 */
function reviveUnderOldName(object $current, string $oldClass): object
{
    $payload = serialize($current);
    $newClass = $current::class;
    $legacy = str_replace(
        sprintf('O:%d:"%s"', strlen($newClass), $newClass),
        sprintf('O:%d:"%s"', strlen($oldClass), $oldClass),
        $payload,
    );

    return unserialize($legacy);
}

it('revives embed jobs queued under their old Conversation names', function (object $job, string $oldClass, string $property): void {
    $revived = reviveUnderOldName($job, $oldClass);

    expect($revived)->toBeInstanceOf($oldClass)
        ->toBeInstanceOf($job::class)
        ->and($revived->{$property})->toBe($job->{$property});
})->with([
    'connector tools' => [new EmbedConnectorToolsJob('acme-crm'), 'Domain\Conversation\Jobs\EmbedConnectorToolsJob', 'connectorSlug'],
    'function' => [new EmbedFunctionJob(42), 'Domain\Conversation\Jobs\EmbedFunctionJob', 'functionId'],
    'mcp tools' => [new EmbedMcpToolsJob(7), 'Domain\Conversation\Jobs\EmbedMcpToolsJob', 'mcpServerId'],
]);

it('revives an McpToolInvoked event queued under its old Analytics name', function (): void {
    $event = new McpToolInvoked(1, 2, 3, 'crm', 'lookup', null, 'CRM', null, null, now());

    $revived = reviveUnderOldName($event, 'Domain\Analytics\Events\McpToolInvoked');

    expect($revived)->toBeInstanceOf(McpToolInvoked::class)
        ->and($revived->serverSlug)->toBe('crm')
        ->and($revived->toolName)->toBe('lookup');
});
