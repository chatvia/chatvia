<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Conversation retention override (chatvia:prune:conversations)
|--------------------------------------------------------------------------
|
| A workspace can SHORTEN chat retention below the install's retention
| (`chatvia.retention.conversations_days`, ConversationRetention::days()) via
| WorkspaceSecurityPolicy.retention_conversations_days. The daily sweep soft-
| deletes conversations idle past that override window (anchored on updated_at),
| on top of the expires_at sweep.
*/

function crpAgedConversation(Agent $agent, int $daysIdle): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id]);

    // Push last activity into the past without tripping the updated_at touch.
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['updated_at' => now()->subDays($daysIdle)]);

    return $conversation;
}

it('prunes conversations idle past a workspace retention override', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_conversations_days' => 5]);

    $old = crpAgedConversation($agent, daysIdle: 30);
    $recent = crpAgedConversation($agent, daysIdle: 1);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    expect($old->fresh()->trashed())->toBeTrue()
        ->and($recent->fresh()->trashed())->toBeFalse();
});

it('clamps an override longer than the install\'s retention down to it', function (): void {
    config(['chatvia.retention.conversations_days' => 90]);
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_conversations_days' => 9999]);

    // Idle 120 days: past the install's 90, well under the override's 9999.
    $conversation = crpAgedConversation($agent, daysIdle: 120);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    // Pruned because the override is clamped to the install's retention, not trusted verbatim.
    expect($conversation->fresh()->trashed())->toBeTrue();
});

it('leaves conversations untouched when the workspace has no override', function (): void {
    $agent = Agent::factory()->create();
    // No policy override and no expires_at — age alone must not prune it.
    $conversation = crpAgedConversation($agent, daysIdle: 400);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    expect($conversation->fresh()->trashed())->toBeFalse();
});
