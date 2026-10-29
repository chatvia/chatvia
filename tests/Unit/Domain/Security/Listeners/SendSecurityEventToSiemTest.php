<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\SendSecurityEventToSiem;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * The listener that sends security events to a workspace's SIEM webhook
 * queues a delivery on the analytics queue, delivers it when the job runs,
 * and counts a delivery that ran out of retries as a failure.
 */
beforeEach(function (): void {
    withEnterpriseLicense();
    $this->owner = User::factory()->create();
    $this->policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id);
    $this->policy->update(['is_webhook_enabled' => true, 'webhook_url' => 'https://siem.example.com/ingest', 'webhook_secret' => 'shh-secret', 'webhook_failures' => 0]);
    $this->event = SecurityEventOccurred::for(
        action: SecurityAuditAction::PolicyChanged,
        workspaceId: $this->owner->workspace()->id,
        actor: SecurityActorData::system('203.0.113.7'),
    );
});

it('queues an event the webhook takes on the analytics queue and delivers it when the job runs', function (): void {
    Queue::fake();
    Http::fake(['*' => Http::response('', 200)]);

    event($this->event);
    app(SendSecurityEventToSiem::class)->handle($this->event);

    Queue::assertPushedOn('analytics', CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === SendSecurityEventToSiem::class);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://siem.example.com/ingest');
});

it('counts a delivery that ran out of retries as a failure', function (): void {
    app(SendSecurityEventToSiem::class)->failed($this->event, new RuntimeException('boom'));

    expect($this->policy->fresh()->webhook_failures)->toBe(1);
});
