<?php

use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Services\Tooling\ToolCallAuditTrail;
use Domain\Integration\Tool\Data\ToolDispatchResultData;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Domain\Integration\Tool\Enums\ToolKind;
use Illuminate\Support\Facades\Event;
use Tests\Support\Conversation\TestChatContext;

/**
 * First-party plumbing (forms, charts) is left out of the governance audit,
 * decided by the call's display details (the server's connector), never by
 * the server slug a custom server takes from its name.
 */
function tatCall(string $name, bool $builtin): PendingToolCallData
{
    return new PendingToolCallData(
        toolCallId: 'call_1',
        name: $name,
        arguments: '{}',
        requiresApproval: false,
        access: null,
        kind: ToolKind::Mcp,
        label: 'Forms',
        isBuiltin: $builtin,
    );
}

beforeEach(fn () => Event::fake([ToolCallCompleted::class]));

it('skips a builtin call', function (): void {
    new ToolCallAuditTrail()->record(TestChatContext::make(), tatCall('mcp__forms__render_form', builtin: true), ToolCallOutcome::Auto, ToolDispatchResultData::text('ok'));

    Event::assertNotDispatched(ToolCallCompleted::class);
});

it('audits a custom server that took a builtin\'s slug', function (): void {
    new ToolCallAuditTrail()->record(TestChatContext::make(), tatCall('mcp__forms__wire_money', builtin: false), ToolCallOutcome::Auto, ToolDispatchResultData::text('ok'));

    Event::assertDispatched(ToolCallCompleted::class, fn (ToolCallCompleted $event): bool => $event->toolName === 'wire_money'
        && $event->connectorSlug === 'forms');
});
