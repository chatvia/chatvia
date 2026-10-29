<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterPasswordResetTokens;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The scheduled retention commands run their Action: each one changes or
 * removes the data past its window, the workspace's own window where it has
 * one. What the Actions keep and remove is tested next to them in
 * tests/Unit.
 */
beforeEach(fn () => withEnterpriseLicense());

it('anonymizes sessions and deletes analytics and leads past the workspace\'s windows', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update([
        'anonymize_after_days' => 30,
        'retention_analytics_days' => 30,
        'retention_leads_days' => 30,
    ]);
    $session = Session::factory()->create(['agent_id' => $agent->id, 'email' => 'dana@example.com']);
    DB::table('chat_sessions')->where('id', $session->id)->update(['created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)]);
    analyticsRollup($agent, now()->subDays(45)->startOfHour()->toDateTimeString());
    $lead = FormSubmission::factory()->forDefinition(FormDefinition::factory()->forAgent($agent)->create())->submitted()->create(['submitted_at' => now()->subDays(60)]);

    $this->artisan('chatvia:privacy:anonymize-sessions', ['--batch' => 1])->assertSuccessful();
    $this->artisan('chatvia:prune:workspace-analytics', ['--batch' => 1])->assertSuccessful();
    $this->artisan('chatvia:prune:leads', ['--batch' => 1])->assertSuccessful();

    expect(DB::table('chat_sessions')->where('id', $session->id)->first())
        ->anonymized_at->not->toBeNull()
        ->email->toBeNull()
        ->and(DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_hourly_rollups')->where('agent_id', $agent->id)->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey($lead->id)->exists())->toBeFalse();
});

it('deletes stale uploads with their files and expired chatter reset tokens', function (): void {
    Storage::fake(Attachment::DISK);
    $upload = Attachment::factory()->pending(Agent::factory()->create())->stored()->create();
    DB::table('chat_attachments')->where('id', $upload->id)->update(['created_at' => now()->subDays(31)]);
    $chatter = Chatter::factory()->create();
    app(ChatterPasswordResetTokens::class)->create($chatter);
    DB::table('chatter_password_reset_tokens')->where('chatter_id', $chatter->id)->update(['created_at' => now()->subHours(2)]);

    $this->artisan('chatvia:prune:attachments')->assertSuccessful();
    $this->artisan('chatvia:prune:chatter-password-resets')->assertSuccessful();

    expect(Attachment::query()->find($upload->id))->toBeNull()
        ->and(DB::table('chatter_password_reset_tokens')->where('chatter_id', $chatter->id)->exists())->toBeFalse();
    Storage::disk(Attachment::DISK)->assertMissing($upload->filepath);
});

it('queues the purge of agents deleted long ago with chatvia:agent:purge-deleted', function (): void {
    Bus::fake([PurgeAgentJob::class]);
    $agent = Agent::factory()->create();
    $agent->delete();
    $agent->forceFill(['deleted_at' => now()->subDays(2)])->saveQuietly();

    $this->artisan('chatvia:agent:purge-deleted')->assertSuccessful();

    Bus::assertDispatched(PurgeAgentJob::class, fn (PurgeAgentJob $job): bool => $job->agentId === $agent->id);
});
