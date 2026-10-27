<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceToolCatalog;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolEntryData;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * The tools a voice call offers: the agent's tools, those that need approval
 * included (the call asks out loud), each named for the call view by its
 * connector and action.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->conversation = Conversation::factory()->forAgent($this->agent)->hostedChat()->create();

    $this->tool = 'mcp__stripe__send_stripe_feedback';
    $this->mock(ToolRegistry::class)->shouldReceive('for')->andReturn([
        new ToolEntryData(ToolKind::Mcp, '1', $this->tool, 'Send feedback to Stripe.', ['type' => 'object', 'properties' => ['feedback' => ['type' => 'string']]]),
    ]);
    $this->mock(ToolApprovalPolicy::class)->shouldReceive('requiresApproval')->andReturn(true);
});

it('offers a tool that needs approval to a signed-in caller only', function (): void {
    $catalog = app(VoiceToolCatalog::class);

    expect($catalog->for($this->agent, $this->conversation->external_id, ChatViewerData::chatter(1)))->toHaveKey($this->tool)
        ->and($catalog->for($this->agent, $this->conversation->external_id, ChatViewerData::staff(1)))->toHaveKey($this->tool)
        // An anonymous visitor's yes would run it with the workspace's credentials.
        ->and($catalog->for($this->agent, $this->conversation->external_id))->not->toHaveKey($this->tool);
});

it('names a tool for the call view by its connector and action', function (): void {
    $catalog = app(VoiceToolCatalog::class);
    $labels = $catalog->labels($this->agent, $catalog->for($this->agent, $this->conversation->external_id, ChatViewerData::chatter(1)));

    expect($labels)->toHaveKey($this->tool)
        ->and($labels[$this->tool]->kind)->toBe(ToolKind::Mcp)
        ->and($labels[$this->tool]->label)->toBe('stripe')
        ->and($labels[$this->tool]->action)->toBe('send_stripe_feedback');
});

it('offers the knowledge search only to an agent that searches its knowledge', function (): void {
    $catalog = app(VoiceToolCatalog::class);
    $tools = $catalog->for($this->agent, $this->conversation->external_id, ChatViewerData::chatter(1));

    expect(array_column($catalog->definitions($this->agent, $tools), 'name'))->toBe([VoiceToolCatalog::KNOWLEDGE_TOOL, $this->tool]);

    $this->agent->setSetting('search', false, 'knowledge');

    expect(array_column($catalog->definitions($this->agent->fresh(), $tools), 'name'))->toBe([$this->tool]);
});
