<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\DeliverSecurityWebhookAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\SendSecurityEventToSiem;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\WebhookSignature;

/**
 * SIEM webhook delivery — HMAC-signed POST, enabled + allowlist gating, and
 * the listener's failure tracking.
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
    $policy = WorkspaceSecurityPolicy::forWorkspace($workspace->id);
    $policy->update(array_merge([
        'webhook_enabled' => true,
        'webhook_url' => 'https://siem.example.com/ingest',
        'webhook_secret' => 'shh-secret',
    ], $overrides));

    return $policy;
}

it('delivers a signed POST and resets the failure counter', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $workspace = User::factory()->create();
    $policy = enableSiem($workspace, ['webhook_failures' => 4]);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://siem.example.com/ingest'
        && str_starts_with($request->header('X-Chatvia-Signature')[0], 'sha256='));

    expect($policy->fresh()->webhook_failures)->toBe(0)
        ->and($policy->fresh()->webhook_last_delivered_at)->not->toBeNull();
});

it('signs the exact body it sends with the shared webhook signature', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $workspace = User::factory()->create();
    enableSiem($workspace);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id));

    Http::assertSent(fn ($request): bool => $request->header('X-Chatvia-Signature')[0] === 'sha256='.WebhookSignature::sign($request->body(), 'shh-secret')
        && $request->header('X-Chatvia-Event')[0] === SecurityAuditAction::PolicyChanged->value);
});

it('re-checks the stored URL at send time and refuses an internal target', function (): void {
    // A URL saved while it looked public (or written before the save-time
    // check existed) must still be refused when it now points inside.
    app()['env'] = 'production';
    Http::fake();
    $workspace = User::factory()->create();
    $policy = enableSiem($workspace, ['webhook_url' => 'http://169.254.169.254/latest/meta-data', 'webhook_failures' => 1]);

    expect(fn () => app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id)))
        ->toThrow(UnsafeUrlException::class);

    Http::assertNothingSent();
    expect($policy->fresh()->webhook_failures)->toBe(1)
        ->and($policy->fresh()->webhook_last_delivered_at)->toBeNull();
});

it('treats a redirect as a failed delivery so the queue retries', function (): void {
    Http::fake(['*' => Http::response('', 301, ['Location' => 'http://10.0.0.5/'])]);
    $workspace = User::factory()->create();
    enableSiem($workspace);

    expect(fn () => app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id)))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
});

it('does not deliver when the webhook is disabled', function (): void {
    Http::fake();
    $workspace = User::factory()->create();
    enableSiem($workspace, ['webhook_enabled' => false]);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id));

    Http::assertNothingSent();
});

it('respects the per-event allowlist', function (): void {
    Http::fake();
    $workspace = User::factory()->create();
    enableSiem($workspace, ['webhook_events' => [SecurityAuditAction::PolicyRetentionChanged->value]]);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id)); // PolicyChanged — not allowlisted

    Http::assertNothingSent();
});

it('throws on a non-2xx response so the queue retries', function (): void {
    Http::fake(['*' => Http::response('nope', 500)]);
    $workspace = User::factory()->create();
    enableSiem($workspace);

    expect(fn () => app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id)))
        ->toThrow(RequestException::class);
});

it('auto-disables the webhook once consecutive failures hit the threshold', function (): void {
    config(['chatvia.siem.max_failures' => 3]);
    $workspace = User::factory()->create();
    $policy = enableSiem($workspace, ['webhook_failures' => 2]);

    app(SendSecurityEventToSiem::class)->failed(siemEvent($workspace->id), new RuntimeException('boom'));

    $policy->refresh();
    expect($policy->webhook_failures)->toBe(3)
        ->and($policy->webhook_enabled)->toBeFalse();
});
