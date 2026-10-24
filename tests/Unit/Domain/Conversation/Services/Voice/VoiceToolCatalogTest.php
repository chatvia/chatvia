<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceToolCatalog;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolEntry;
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
        new ToolEntry(ToolKind::Mcp, '1', $this->tool, 'Send feedback to Stripe.', ['type' => 'object', 'properties' => ['feedback' => ['type' => 'string']]]),
    ]);
    $this->mock(ToolApprovalPolicy::class)->shouldReceive('requiresApproval')->andReturn(true);
});

it('offers a tool that needs approval in the call', function (): void {
    expect(app(VoiceToolCatalog::class)->for($this->agent, $this->conversation->external_id))->toHaveKey($this->tool);
});

it('names a tool for the call view by its connector and action', function (): void {
    $catalog = app(VoiceToolCatalog::class);
    $labels = $catalog->labels($this->agent, $catalog->for($this->agent, $this->conversation->external_id));

    expect($labels)->toHaveKey($this->tool)
        ->and($labels[$this->tool]->kind)->toBe(ToolKind::Mcp)
        ->and($labels[$this->tool]->label)->toBe('stripe')
        ->and($labels[$this->tool]->action)->toBe('send_stripe_feedback');
});
