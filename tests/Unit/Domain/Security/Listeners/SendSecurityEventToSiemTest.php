<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\SendSecurityEventToSiem;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;

/**
 * The listener that sends security events to a workspace's SIEM webhook
 * queues a delivery only when the webhook is on and takes the event, and
 * counts a delivery that ran out of retries as a failure.
 */

/** A policy change of the workspace. */
function sseEvent(int $workspaceId): SecurityEventOccurred
{
    return SecurityEventOccurred::for(
        action: SecurityAuditAction::PolicyChanged,
        workspaceId: $workspaceId,
        actor: SecurityActorData::system('203.0.113.7'),
        metadata: ['k' => 'v'],
    );
}

/**
 * The owner's workspace policy with the SIEM webhook on, and these columns.
 *
 * @param  array<string, mixed>  $overrides
 */
function sseEnableSiem(User $owner, array $overrides = []): WorkspaceSecurityPolicy
{
    $policy = WorkspaceSecurityPolicy::forWorkspace($owner->workspace()->id);
    $policy->update(['is_webhook_enabled' => true, 'webhook_url' => 'https://siem.example.com/ingest', 'webhook_secret' => 'shh-secret', ...$overrides]);

    return $policy;
}

/** Whether a queued job is this listener's. */
function sseQueued(CallQueuedListener $job): bool
{
    return $job->class === SendSecurityEventToSiem::class;
}

beforeEach(function (): void {
    withEnterpriseLicense();
    Queue::fake();
    $this->owner = User::factory()->create();
});

it('queues a delivery for an event the webhook takes', function (): void {
    sseEnableSiem($this->owner);

    event(sseEvent($this->owner->workspace()->id));

    Queue::assertPushed(CallQueuedListener::class, sseQueued(...));
});

it('queues nothing when the webhook would not take the event', function (Closure $policy): void {
    $policy($this->owner);

    event(sseEvent($this->owner->workspace()->id));

    Queue::assertNotPushed(CallQueuedListener::class, sseQueued(...));
})->with([
    'no SIEM webhook' => [fn (): null => null],
    'an allowlist that leaves the event out' => [fn (User $owner): WorkspaceSecurityPolicy => sseEnableSiem($owner, ['webhook_events' => [SecurityAuditAction::PolicyRetentionChanged->value]])],
]);

it('counts a delivery that ran out of retries as a failure', function (): void {
    $policy = sseEnableSiem($this->owner, ['webhook_failures' => 0]);

    app(SendSecurityEventToSiem::class)->failed(sseEvent($this->owner->workspace()->id), new RuntimeException('boom'));

    expect($policy->fresh()->webhook_failures)->toBe(1);
});
