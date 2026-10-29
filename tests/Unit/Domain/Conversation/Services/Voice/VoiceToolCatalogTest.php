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
 * A voice call offers a tool that needs approval (the call asks out loud)
 * only to a signed-in caller: an anonymous visitor's yes would run it with
 * the workspace's credentials.
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
        ->and($catalog->for($this->agent, $this->conversation->external_id))->not->toHaveKey($this->tool);
});
