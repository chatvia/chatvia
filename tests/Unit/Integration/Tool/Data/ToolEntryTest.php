<?php

use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Domain\Integration\Tool\Enums\ToolKind;

function toolEntryNamed(string $name, ToolKind $kind): ToolEntry
{
    return new ToolEntry(kind: $kind, sourceId: 'x:'.$name, name: $name, description: '', parameters: []);
}

it('matches an MCP tool to the servers it may come from', function (): void {
    $entry = toolEntryNamed('mcp__crm__lookup', ToolKind::Mcp);

    expect($entry->isFromAnyServer(['billing', 'crm']))->toBeTrue()
        ->and($entry->isFromAnyServer(['billing']))->toBeFalse()
        ->and($entry->isFromAnyServer([]))->toBeFalse();
});

it('never matches a webhook function to a server, whatever its name', function (): void {
    expect(toolEntryNamed('mcp__crm__lookup', ToolKind::Function)->isFromAnyServer(['crm']))->toBeFalse();
});

it('decodes a JSON-schema column into an array', function (): void {
    expect(ToolEntry::decodeSchema('{"type":"object"}'))->toBe(['type' => 'object'])
        ->and(ToolEntry::decodeSchema(['type' => 'object']))->toBe(['type' => 'object'])
        ->and(ToolEntry::decodeSchema(null))->toBe([])
        ->and(ToolEntry::decodeSchema('not json'))->toBe([]);
});

it('ships an empty schema as an object with no properties', function (): void {
    $format = toolEntryNamed('get_time', ToolKind::Function)->toOpenAiFormat();

    expect(json_encode($format['function']['parameters']))->toBe('{"type":"object","properties":{}}');
});

it('flags only human-decided outcomes as gated', function (): void {
    expect(ToolCallOutcome::Approved->wasGated())->toBeTrue()
        ->and(ToolCallOutcome::Denied->wasGated())->toBeTrue()
        ->and(ToolCallOutcome::Auto->wasGated())->toBeFalse()
        ->and(ToolCallOutcome::RateLimited->wasGated())->toBeFalse();
});
