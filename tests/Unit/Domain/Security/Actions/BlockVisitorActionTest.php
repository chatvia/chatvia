<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Security\Actions\BlockVisitorAction;
use Domain\Security\Data\BlockedVisitorRowData;
use Domain\Security\Data\BlockVisitorData;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\VisitorBlockDuration;
use Domain\Security\Models\SecurityAuditLog;
use Illuminate\Support\Str;

/**
 * Blocking a visitor lasts the chosen time, notes the browser and system of
 * the attack's chat session but never its address, and writes the block to
 * the security audit log.
 */
it('blocks the visitor for the chosen time without keeping their address and logs the block', function (VisitorBlockDuration $duration, int $days): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $session = Session::factory()->create(['agent_id' => $agent->id, 'browser' => 'Firefox', 'os' => 'Linux', 'ip_address' => '203.0.113.9']);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id]);

    $block = app(BlockVisitorAction::class)->execute(new BlockVisitorData(
        workspaceId: $agent->workspace_id,
        visitorKey: Str::repeat('a', 64),
        duration: $duration,
        actor: SecurityActorData::forUser($owner),
        agentId: $agent->id,
        conversationExternalId: $conversation->external_id,
    ));

    expect($block->expires_at->equalTo(now()->addDays($days)))->toBeTrue()
        ->and($block->browser)->toBe('Firefox')
        ->and(json_encode(BlockedVisitorRowData::fromModel($block), JSON_THROW_ON_ERROR))->not->toContain('203.0.113.9')
        ->and(SecurityAuditLog::query()->where('workspace_id', $agent->workspace_id)->where('action', 'visitor.blocked')->sole()->target_id)->toBe($block->external_id);
})->with([
    'a week' => [VisitorBlockDuration::Week, 7],
    'a month of 30 days' => [VisitorBlockDuration::Month, 30],
]);
