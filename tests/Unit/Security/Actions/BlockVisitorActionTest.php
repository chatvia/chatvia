<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Security\Actions\BlockVisitorAction;
use Domain\Security\Data\BlockVisitorData;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\VisitorBlockDuration;
use Domain\Security\Models\BlockedVisitor;
use Domain\Security\Models\SecurityAuditLog;

/**
 * Blocking a visitor keeps one block per fingerprint (blocking again updates
 * it), notes the browser, system and country of the attack's chat session
 * but never the address, and writes the block to the security audit log.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
    $this->session = Session::factory()->create(['agent_id' => $this->agent->id, 'browser' => 'Firefox', 'os' => 'Linux', 'ip_address' => '203.0.113.9']);
    $this->conversation = Conversation::factory()->create(['chat_session_id' => $this->session->id]);
});

function bvaBlock(VisitorBlockDuration $duration, ?string $reason = null): BlockedVisitor
{
    return app(BlockVisitorAction::class)->execute(new BlockVisitorData(
        workspaceId: test()->agent->workspace_id,
        visitorKey: str_repeat('a', 64),
        duration: $duration,
        actor: SecurityActorData::forUser(test()->owner),
        agentId: test()->agent->id,
        conversationExternalId: test()->conversation->external_id,
        reason: $reason,
    ));
}

it('blocks the visitor for the chosen time and notes what they used', function (): void {
    $this->freezeSecond();

    $block = bvaBlock(VisitorBlockDuration::Week, 'Kept asking for the prompt');

    expect($block->expires_at->equalTo(now()->addWeek()))->toBeTrue()
        ->and($block->browser)->toBe('Firefox')
        ->and($block->os)->toBe('Linux')
        ->and($block->agent_id)->toBe($this->agent->id)
        ->and($block->blocked_by_user_id)->toBe($this->owner->id)
        ->and($block->reason)->toBe('Kept asking for the prompt')
        ->and(json_encode($block->toListArray()))->not->toContain('203.0.113.9');
});

it('updates the block when the visitor is blocked again', function (): void {
    bvaBlock(VisitorBlockDuration::Day);
    $block = bvaBlock(VisitorBlockDuration::Forever);

    expect(BlockedVisitor::query()->where('workspace_id', $this->agent->workspace_id)->count())->toBe(1)
        ->and($block->expires_at)->toBeNull();
});

it('writes the block to the security audit log', function (): void {
    $block = bvaBlock(VisitorBlockDuration::Month);

    $row = SecurityAuditLog::query()->where('workspace_id', $this->agent->workspace_id)->where('action', 'visitor.blocked')->sole();

    expect($row->target_id)->toBe($block->external_id)
        ->and($row->user_id)->toBe($this->owner->id)
        ->and($row->outcome)->toBe('success');
});
