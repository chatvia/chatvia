<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterPasswordResetTokens;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserKnownDevice;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The scheduled retention commands run their Action: each one changes a row
 * past its workspace's window. What the Actions keep and remove is tested
 * next to them in tests/Unit.
 */
it('anonymizes old visitor sessions with chatvia:privacy:anonymize-sessions', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['anonymize_after_days' => 30]);
    $session = Session::factory()->create(['agent_id' => $agent->id, 'email' => 'dana@example.com']);
    DB::table('chat_sessions')->where('id', $session->id)->update(['created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)]);

    $this->artisan('chatvia:privacy:anonymize-sessions', ['--batch' => 1])->assertSuccessful();

    expect(DB::table('chat_sessions')->where('id', $session->id)->first())
        ->anonymized_at->not->toBeNull()
        ->email->toBeNull();
});

it('deletes stale uploads that joined no conversation with chatvia:prune:attachments', function (): void {
    Storage::fake(Attachment::DISK);
    Storage::disk(Attachment::DISK)->put('users/x/agents/y/attachments/doc.pdf', 'pdf');
    $upload = Attachment::create(['workspace_id' => Agent::factory()->create()->workspace_id, 'filepath' => 'users/x/agents/y/attachments/doc.pdf', 'filename' => 'doc.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 3]);
    DB::table('chat_attachments')->where('id', $upload->id)->update(['created_at' => now()->subDays(31)]);

    $this->artisan('chatvia:prune:attachments')->assertSuccessful();

    expect(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing('users/x/agents/y/attachments/doc.pdf');
});

it('deletes leads past the workspace\'s window with chatvia:prune:leads', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_leads_days' => 30]);
    $lead = FormSubmission::factory()->forDefinition(FormDefinition::factory()->forAgent($agent)->create())->submitted()->create(['submitted_at' => now()->subDays(60)]);

    $this->artisan('chatvia:prune:leads', ['--batch' => 1])->assertSuccessful();

    expect(FormSubmission::query()->whereKey($lead->id)->exists())->toBeFalse();
});

it('forgets sign-in devices unused for 180 days with chatvia:prune:known-devices', function (): void {
    $user = User::factory()->create();
    $device = UserKnownDevice::query()->create(['user_id' => $user->id, 'fingerprint_hash' => Str::repeat('a', 64), 'last_seen_at' => now()->subDays(200)]);

    $this->artisan('chatvia:prune:known-devices')->assertSuccessful();

    expect(UserKnownDevice::query()->whereKey($device->id)->exists())->toBeFalse();
});

it('deletes chatter and team invitations long expired with chatvia:prune:invitations', function (): void {
    $agent = Agent::factory()->create();
    $chatterInvitation = AgentChatterInvitation::query()->create(['agent_id' => $agent->id, 'email' => 'old@example.test', 'token' => Str::repeat('b', 64), 'expires_at' => now()->subDays(40)]);
    $team = app(TeamService::class)->createDefaultTeam(User::factory()->create());
    $teamInvitation = $team->invitations()->create(['role_id' => $team->roles()->firstOrFail()->id, 'email' => 'old@example.test']);
    $teamInvitation->forceFill(['created_at' => now()->subDays(40)])->save();

    $this->artisan('chatvia:prune:invitations')->assertSuccessful();

    expect(AgentChatterInvitation::query()->whereKey($chatterInvitation->id)->exists())->toBeFalse()
        ->and(Invitation::query()->whereKey($teamInvitation->id)->exists())->toBeFalse();
});

it('deletes expired chatter reset tokens with chatvia:prune:chatter-password-resets', function (): void {
    $chatter = Chatter::factory()->create();
    app(ChatterPasswordResetTokens::class)->create($chatter);
    DB::table('chatter_password_reset_tokens')->where('chatter_id', $chatter->id)->update(['created_at' => now()->subHours(2)]);

    $this->artisan('chatvia:prune:chatter-password-resets')->assertSuccessful();

    expect(DB::table('chatter_password_reset_tokens')->where('chatter_id', $chatter->id)->exists())->toBeFalse();
});

it('queues the purge of agents deleted long ago with chatvia:agent:purge-deleted', function (): void {
    Bus::fake([PurgeAgentJob::class]);
    $agent = Agent::factory()->create();
    $agent->delete();
    $agent->forceFill(['deleted_at' => now()->subDays(2)])->saveQuietly();

    $this->artisan('chatvia:agent:purge-deleted')->assertSuccessful();

    Bus::assertDispatched(PurgeAgentJob::class, fn (PurgeAgentJob $job): bool => $job->agentId === $agent->id);
});
