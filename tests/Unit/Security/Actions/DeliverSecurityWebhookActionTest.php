<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\DeliverSecurityWebhookAction;
use Domain\Security\Actions\RecordSiemDeliveryFailureAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\SendSecurityEventToSiem;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\WebhookSignature;

/**
 * SIEM webhook delivery: what the action decides (whether to send, with the
 * policy's URL, secret and the event name; the failure counter reset on
 * success, and kept on a refused URL) and the listener's retry and failure
 * hand-over. Signing, redirects and the SSRF list are SignedWebhookSender's
 * and are tested there.
 */
function siemEvent(int $workspaceId): SecurityEventOccurred
{
    return SecurityEventOccurred::for(
        action: SecurityAuditAction::PolicyChanged,
        workspaceId: $workspaceId,
        actor: SecurityActorData::system('203.0.113.7'),
        metadata: ['k' => 'v'],
    );
}

function enableSiem(User $workspace, array $overrides = []): WorkspaceSecurityPolicy
{
    $policy = WorkspaceSecurityPolicy::forWorkspace($workspace->workspace()->id);
    $policy->update(array_merge([
        'webhook_enabled' => true,
        'webhook_url' => 'https://siem.example.com/ingest',
        'webhook_secret' => 'shh-secret',
    ], $overrides));

    return $policy;
}

it('delivers to the policy URL with its secret and resets the failure counter', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $workspace = User::factory()->create();
    $policy = enableSiem($workspace, ['webhook_failures' => 4]);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->workspace()->id));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://siem.example.com/ingest'
        && $request->header('X-Chatvia-Signature')[0] === 'sha256='.WebhookSignature::sign($request->body(), 'shh-secret')
        && $request->header('X-Chatvia-Event')[0] === SecurityAuditAction::PolicyChanged->value);

    expect($policy->fresh()->webhook_failures)->toBe(0)
        ->and($policy->fresh()->webhook_last_delivered_at)->not->toBeNull();
});

it('re-checks the stored URL at send time and refuses an internal target', function (): void {
    // A URL saved while it looked public must still be refused when it now
    // points inside, and the refusal does not count as a delivery.
    app()['env'] = 'production';
    Http::fake();
    $workspace = User::factory()->create();
    $policy = enableSiem($workspace, ['webhook_url' => 'http://169.254.169.254/latest/meta-data', 'webhook_failures' => 1]);

    expect(fn () => app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->workspace()->id)))
        ->toThrow(UnsafeUrlException::class);

    Http::assertNothingSent();
    expect($policy->fresh()->webhook_failures)->toBe(1)
        ->and($policy->fresh()->webhook_last_delivered_at)->toBeNull();
});

it('does not deliver when the webhook is disabled', function (): void {
    Http::fake();
    $workspace = User::factory()->create();
    enableSiem($workspace, ['webhook_enabled' => false]);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->workspace()->id));

    Http::assertNothingSent();
});

it('respects the per-event allowlist', function (): void {
    Http::fake();
    $workspace = User::factory()->create();
    enableSiem($workspace, ['webhook_events' => [SecurityAuditAction::PolicyRetentionChanged->value]]);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->workspace()->id)); // PolicyChanged — not allowlisted

    Http::assertNothingSent();
});

it('throws on a non-2xx response so the queue retries', function (): void {
    Http::fake(['*' => Http::response('nope', 500)]);
    $workspace = User::factory()->create();
    enableSiem($workspace);

    expect(fn () => app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->workspace()->id)))
        ->toThrow(RequestException::class);
});

it('retries a failed delivery with a growing pause', function (): void {
    Queue::fake();

    event(siemEvent(1));

    Queue::assertPushed(
        CallQueuedListener::class,
        fn (CallQueuedListener $job): bool => $job->class === SendSecurityEventToSiem::class
            && $job->tries === 3
            && $job->backoff === [10, 60, 300],
    );
});

it('hands a delivery that ran out of retries to the failure counter', function (): void {
    $this->mock(RecordSiemDeliveryFailureAction::class)
        ->shouldReceive('execute')->once()->with(42);

    app(SendSecurityEventToSiem::class)->failed(siemEvent(42), new RuntimeException('boom'));
});
