<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Conversation retention override (app:prune-expired-conversations)
|--------------------------------------------------------------------------
|
| Enterprise workspaces can SHORTEN chat retention below their plan ceiling via
| WorkspaceSecurityPolicy.retention_conversations_days. The daily sweep soft-
| deletes conversations idle past that override window (anchored on updated_at),
| on top of the existing plan-default expires_at sweep.
*/

function agedConversation(Agent $agent, int $daysIdle): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id]);

    // Push last activity into the past without tripping the updated_at touch.
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['updated_at' => now()->subDays($daysIdle)]);

    return $conversation;
}

it('prunes conversations idle past a workspace retention override', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->user_id)->update(['retention_conversations_days' => 5]);

    $old = agedConversation($agent, daysIdle: 30);
    $recent = agedConversation($agent, daysIdle: 1);

    $this->artisan('app:prune-expired-conversations')->assertSuccessful();

    expect($old->fresh()->trashed())->toBeTrue()
        ->and($recent->fresh()->trashed())->toBeFalse();
});

it('clamps a stale over-plan override down to the current plan ceiling', function (): void {
    $agent = Agent::factory()->create();
    // A stale override left from a higher plan — far above any current ceiling.
    WorkspaceSecurityPolicy::forWorkspace($agent->user_id)->update(['retention_conversations_days' => 9999]);

    // Idle 400 days exceeds every plan ceiling but is well under the stale 9999.
    $conversation = agedConversation($agent, daysIdle: 400);

    $this->artisan('app:prune-expired-conversations')->assertSuccessful();

    // Pruned because the override is clamped to the plan, not trusted verbatim.
    expect($conversation->fresh()->trashed())->toBeTrue();
});

it('leaves conversations untouched when the workspace has no override', function (): void {
    $agent = Agent::factory()->create();
    // No policy override and no expires_at — age alone must not prune it.
    $conversation = agedConversation($agent, daysIdle: 400);

    $this->artisan('app:prune-expired-conversations')->assertSuccessful();

    expect($conversation->fresh()->trashed())->toBeFalse();
});
