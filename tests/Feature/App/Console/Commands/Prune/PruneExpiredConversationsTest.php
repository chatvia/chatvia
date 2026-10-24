<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The daily conversation sweep (chatvia:prune:conversations) with a
 * workspace's own, shorter chat retention (Settings → Security). On top of
 * the expires_at sweep, it soft-deletes a conversation idle (by updated_at)
 * past that window, never past the install's retention, and only in that
 * workspace. The rest of the sweep is tested with
 * PruneExpiredConversationsAction.
 */

/** A conversation of the agent that had messages and was last active $daysIdle ago. */
function pecAgedConversation(Agent $agent, int $daysIdle): Conversation
{
    // Visible: a hidden one is swept by its short expires_at.
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'is_visible' => true]);

    // Push last activity into the past without tripping the updated_at touch.
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['updated_at' => now()->subDays($daysIdle)]);

    return $conversation;
}

it('prunes conversations idle past a workspace retention override', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_conversations_days' => 5]);

    $old = pecAgedConversation($agent, daysIdle: 30);
    $recent = pecAgedConversation($agent, daysIdle: 1);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    expect($old->fresh()->trashed())->toBeTrue()
        ->and($recent->fresh()->trashed())->toBeFalse();
});

it('clamps an override longer than the install\'s retention down to it', function (): void {
    config(['chatvia.retention.conversations_days' => 90]);
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_conversations_days' => 9999]);

    // Idle 120 days: past the install's 90, well under the override's 9999.
    $conversation = pecAgedConversation($agent, daysIdle: 120);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    // Pruned because the override is clamped to the install's retention, not trusted verbatim.
    expect($conversation->fresh()->trashed())->toBeTrue();
});

it('leaves conversations untouched when the workspace has no override', function (): void {
    $agent = Agent::factory()->create();
    // No policy override and no expires_at — age alone must not prune it.
    $conversation = pecAgedConversation($agent, daysIdle: 400);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    expect($conversation->fresh()->trashed())->toBeFalse();
});

it('prunes the conversations of every agent of the workspace, and only of that workspace', function (): void {
    $agent = Agent::factory()->create();
    $sibling = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $stranger = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_conversations_days' => 5]);

    $old = pecAgedConversation($agent, daysIdle: 30);
    $siblingOld = pecAgedConversation($sibling, daysIdle: 30);
    $strangerOld = pecAgedConversation($stranger, daysIdle: 30);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    expect($old->fresh()->trashed())->toBeTrue()
        ->and($siblingOld->fresh()->trashed())->toBeTrue()
        ->and($strangerOld->fresh()->trashed())->toBeFalse();
});

it('sweeps a hidden conversation of an overridden workspace by its own short horizon', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_conversations_days' => 1]);
    $id = app(StartConversationAction::class)->execute(new ChatScopeData($agent, (string) Str::uuid()), ConversationSource::Widget)->external_id;

    $this->travel(Conversation::HIDDEN_LIFETIME_DAYS)->days();
    $this->travel(1)->minutes();
    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    expect(Conversation::withTrashed()->where('external_id', $id)->sole()->trashed())->toBeTrue();
});
