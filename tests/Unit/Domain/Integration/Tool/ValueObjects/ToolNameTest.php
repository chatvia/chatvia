<?php

declare(strict_types=1);

use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\ValueObjects\ToolName;

/**
 * ToolName is the single parse point for the `mcp__{slug}__{tool}` wire
 * format. The dispatchers, the approval policy, the display resolver and the
 * governance audit all read names through it, and names are stored on
 * messages and checkpoints, so these cases pin the exact split rules: the
 * slug ends at the FIRST separator, anything without the shape is a webhook
 * function, and a malformed MCP name stays MCP-shaped but unresolvable.
 */
it('recognises prefixed MCP tool names', function (string $value, string $slug, string $tool): void {
    $name = ToolName::parse($value);

    expect($name->isMcp())->toBeTrue()
        ->and($name->kind)->toBe(ToolKind::Mcp)
        ->and($name->serverSlug)->toBe($slug)
        ->and($name->tool)->toBe($tool)
        ->and($name->hasServerAndTool())->toBeTrue()
        ->and($name->value)->toBe($value);
})->with([
    'a plain name' => ['mcp__stripe__create_customer', 'stripe', 'create_customer'],
    'a tool with a separator of its own' => ['mcp__crm__lookup__by_email', 'crm', 'lookup__by_email'],
]);

it('treats other names as webhook functions, and a malformed MCP name as unresolvable', function (): void {
    $functions = collect(['get_weather', 'mcp_stripe_x', 'mcp__only_slug', ''])->map(ToolName::parse(...));
    $emptySlug = ToolName::parse('mcp____search');
    $emptyTool = ToolName::parse('mcp__stripe__');

    foreach ($functions as $name) {
        expect($name->isMcp())->toBeFalse()
            ->and($name->kind)->toBe(ToolKind::Function)
            ->and($name->serverSlug)->toBeNull()
            ->and($name->tool)->toBe($name->value)
            ->and($name->hasServerAndTool())->toBeFalse();
    }
    expect($emptySlug->isMcp())->toBeTrue()
        ->and($emptySlug->serverSlug)->toBeNull()
        ->and($emptySlug->tool)->toBe('search')
        ->and($emptySlug->hasServerAndTool())->toBeFalse()
        ->and($emptyTool->isMcp())->toBeTrue()
        ->and($emptyTool->serverSlug)->toBe('stripe')
        ->and($emptyTool->tool)->toBe('')
        ->and($emptyTool->hasServerAndTool())->toBeFalse();
});
