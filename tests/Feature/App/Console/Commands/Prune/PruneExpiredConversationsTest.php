<?php

use App\Admin\Settings\BrandSettings;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\DB;

/**
 * The daily conversation sweep (chatvia:prune:conversations) with a
 * workspace's own, shorter chat retention (Settings → Security). On top of
 * the expires_at sweep, it soft-deletes a conversation idle (by updated_at)
 * past that window, never past the install's retention, only in that
 * workspace, and only with an Enterprise license. The rest of the sweep is
 * tested with PruneExpiredConversationsAction.
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

beforeEach(fn () => withEnterpriseLicense());

it('prunes conversations idle past a workspace override in every agent of that workspace, and only there', function (): void {
    $agent = Agent::factory()->create();
    $sibling = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $stranger = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_conversations_days' => 5]);
    $old = pecAgedConversation($agent, daysIdle: 30);
    $recent = pecAgedConversation($agent, daysIdle: 1);
    $siblingOld = pecAgedConversation($sibling, daysIdle: 30);
    $strangerOld = pecAgedConversation($stranger, daysIdle: 400);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    expect($old->fresh()->trashed())->toBeTrue()
        ->and($recent->fresh()->trashed())->toBeFalse()
        ->and($siblingOld->fresh()->trashed())->toBeTrue()
        ->and($strangerOld->fresh()->trashed())->toBeFalse();
});

it('clamps an override longer than the install\'s retention down to it', function (): void {
    config(['chatvia.retention.conversations_days' => 90]);
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_conversations_days' => 9999]);
    $conversation = pecAgedConversation($agent, daysIdle: 120);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    expect($conversation->fresh()->trashed())->toBeTrue();
});

it('ignores a workspace retention override without an Enterprise license', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_conversations_days' => 5]);
    $old = pecAgedConversation($agent, daysIdle: 30);
    saveSettings(BrandSettings::class, ['license_key' => null]);

    $this->artisan('chatvia:prune:conversations')->assertSuccessful();

    expect($old->fresh()->trashed())->toBeFalse();
});
