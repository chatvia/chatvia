<?php

declare(strict_types=1);

use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Enums\ToolKind;

/**
 * ToolName is the single parse/compose point for the `mcp__{slug}__{tool}`
 * wire format. These cases pin the exact split rules the dispatchers, the
 * approval policy, the display resolver and the governance audit used to
 * implement by hand, so the format stays byte-identical.
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
    ['mcp__stripe__create_customer', 'stripe', 'create_customer'],
    ['mcp__github__list_issues', 'github', 'list_issues'],
    ['mcp__forms__render-form-tool', 'forms', 'render-form-tool'],
    // The slug ends at the FIRST separator; the rest belongs to the tool.
    ['mcp__crm__lookup__by_email', 'crm', 'lookup__by_email'],
]);

it('treats anything without the mcp__{slug}__ shape as a webhook function', function (string $value): void {
    $name = ToolName::parse($value);

    expect($name->isMcp())->toBeFalse()
        ->and($name->kind)->toBe(ToolKind::Function)
        ->and($name->serverSlug)->toBeNull()
        ->and($name->tool)->toBe($value)
        ->and($name->hasServerAndTool())->toBeFalse();
})->with([
    'get_weather',
    'mcp_stripe_x',
    'mcp__only_slug',
    '',
]);

it('keeps a malformed MCP name MCP-shaped but not resolvable', function (): void {
    $emptySlug = ToolName::parse('mcp____search');
    $emptyTool = ToolName::parse('mcp__stripe__');

    expect($emptySlug->isMcp())->toBeTrue()
        ->and($emptySlug->serverSlug)->toBeNull()
        ->and($emptySlug->tool)->toBe('search')
        ->and($emptySlug->hasServerAndTool())->toBeFalse()
        ->and($emptyTool->isMcp())->toBeTrue()
        ->and($emptyTool->serverSlug)->toBe('stripe')
        ->and($emptyTool->tool)->toBe('')
        ->and($emptyTool->hasServerAndTool())->toBeFalse();
});

it('composes the exact wire format', function (): void {
    expect(ToolName::forMcp('github', 'search_issues')->value)->toBe('mcp__github__search_issues')
        ->and(ToolName::mcpPrefix('github'))->toBe('mcp__github__')
        ->and((string) ToolName::forMcp('leads', 'capture_lead'))->toBe('mcp__leads__capture_lead');
});

it('matches a server by its tool prefix', function (): void {
    $name = ToolName::parse('mcp__stripe__refund');

    expect($name->isFromServer('stripe'))->toBeTrue()
        ->and($name->isFromServer('strip'))->toBeFalse()
        ->and(ToolName::parse('refund')->isFromServer('stripe'))->toBeFalse();
});

it('applies the LLM function-name rule to the full name', function (): void {
    expect(ToolName::forMcp('stripe', 'create-customer')->isValidForLlm())->toBeTrue()
        ->and(ToolName::forMcp('stripe', 'has space')->isValidForLlm())->toBeFalse()
        ->and(ToolName::forMcp('stripe', str_repeat('a', 60))->isValidForLlm())->toBeFalse()
        ->and(ToolName::parse('1starts_with_digit')->isValidForLlm())->toBeFalse();
});
