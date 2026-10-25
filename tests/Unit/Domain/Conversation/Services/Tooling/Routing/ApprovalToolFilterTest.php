<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Tooling\Routing\ApprovalToolFilter;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolName;
use Illuminate\Support\Str;

/**
 * Only the hosted chat can ask a person to approve a tool call, so every other
 * surface is never offered a tool that needs approval.
 */
function approvalFilterEntry(string $name): ToolEntry
{
    return new ToolEntry(kind: ToolName::parse($name)->kind, sourceId: 'x:'.$name, name: $name, description: '', parameters: []);
}

/** A filter whose policy gates exactly the given tool names. */
function approvalFilter(string ...$gated): ApprovalToolFilter
{
    return new ApprovalToolFilter(new class($gated) extends ToolApprovalPolicy
    {
        /** @param array<int, string> $gated */
        public function __construct(private readonly array $gated) {}

        public function requiresApproval(Agent $agent, ToolName $tool): bool
        {
            return in_array($tool->value, $this->gated, true);
        }
    });
}

/** @param array<int, ToolEntry> $entries */
function approvalFilterNames(array $entries): array
{
    return array_map(static fn (ToolEntry $entry): string => $entry->name, $entries);
}

beforeEach(function (): void {
    $this->entries = [
        approvalFilterEntry('mcp__stripe__create_refund'),
        approvalFilterEntry('mcp__stripe__list_customers'),
        approvalFilterEntry('cancel_order'),
    ];
    $this->filter = approvalFilter('mcp__stripe__create_refund', 'cancel_order');
});

it('keeps tools that need approval in the hosted chat, which can ask for it', function (): void {
    $conversation = Conversation::factory()->create(['source' => ConversationSource::HostedChat]);

    expect(approvalFilterNames($this->filter->apply(new Agent, $this->entries, $conversation->external_id)))
        ->toBe(['mcp__stripe__create_refund', 'mcp__stripe__list_customers', 'cancel_order']);
});

it('drops tools that need approval where no one can approve them', function (ConversationSource $surface): void {
    $conversation = Conversation::factory()->create(['source' => $surface]);

    expect(approvalFilterNames($this->filter->apply(new Agent, $this->entries, $conversation->external_id)))
        ->toBe(['mcp__stripe__list_customers']);
})->with([ConversationSource::Widget, ConversationSource::Bar, ConversationSource::Slack, ConversationSource::Whatsapp]);

it('keeps every tool when the turn has no conversation, or an unknown one', function (): void {
    expect(approvalFilterNames($this->filter->apply(new Agent, $this->entries, null)))->toHaveCount(3)
        ->and(approvalFilterNames($this->filter->apply(new Agent, $this->entries, (string) Str::uuid())))->toHaveCount(3);
});

it('drops tools that need approval for an anonymous visitor on any surface', function (): void {
    $definitions = array_map(static fn (ToolEntry $entry): array => $entry->toOpenAiFormat(), $this->entries);

    expect(array_column(array_column($this->filter->withoutGated(new Agent, $definitions), 'function'), 'name'))
        ->toBe(['mcp__stripe__list_customers']);
});
