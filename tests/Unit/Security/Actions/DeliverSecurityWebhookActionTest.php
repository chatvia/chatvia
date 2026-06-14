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
use Illuminate\Support\Str;

/**
 * SIEM webhook delivery (Enterprise / advanced compliance) — HMAC-signed POST,
 * entitlement + enabled + allowlist gating, and the listener's failure tracking.
 */
function siemEnterpriseWorkspace(): User
{
    $priceId = 'price_test_ent_'.Str::random(6);
    config(['billing.packages.enterprise.stripe_monthly_price_id' => $priceId]);

    $user = User::factory()->create();
    $user->billing()->firstOrCreate([]);
    $user->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
    ]);

    return $user->refresh();
}

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
    $workspace = siemEnterpriseWorkspace();
    $policy = enableSiem($workspace, ['webhook_failures' => 4]);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://siem.example.com/ingest'
        && str_starts_with($request->header('X-Chatvia-Signature')[0], 'sha256='));

    expect($policy->fresh()->webhook_failures)->toBe(0)
        ->and($policy->fresh()->webhook_last_delivered_at)->not->toBeNull();
});

it('does not deliver when the webhook is disabled', function (): void {
    Http::fake();
    $workspace = siemEnterpriseWorkspace();
    enableSiem($workspace, ['webhook_enabled' => false]);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id));

    Http::assertNothingSent();
});

it('does not deliver without the SIEM capability even if configured', function (): void {
    Http::fake();
    $workspace = User::factory()->create(); // starter — no siemWebhook capability
    enableSiem($workspace);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id));

    Http::assertNothingSent();
});

it('respects the per-event allowlist', function (): void {
    Http::fake();
    $workspace = siemEnterpriseWorkspace();
    enableSiem($workspace, ['webhook_events' => [SecurityAuditAction::PolicyRetentionChanged->value]]);

    app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id)); // PolicyChanged — not allowlisted

    Http::assertNothingSent();
});

it('throws on a non-2xx response so the queue retries', function (): void {
    Http::fake(['*' => Http::response('nope', 500)]);
    $workspace = siemEnterpriseWorkspace();
    enableSiem($workspace);

    expect(fn () => app(DeliverSecurityWebhookAction::class)->execute(siemEvent($workspace->id)))
        ->toThrow(RequestException::class);
});

it('auto-disables the webhook once consecutive failures hit the threshold', function (): void {
    config(['chatvia.siem.max_failures' => 3]);
    $workspace = siemEnterpriseWorkspace();
    $policy = enableSiem($workspace, ['webhook_failures' => 2]);

    app(SendSecurityEventToSiem::class)->failed(siemEvent($workspace->id), new RuntimeException('boom'));

    $policy->refresh();
    expect($policy->webhook_failures)->toBe(3)
        ->and($policy->webhook_enabled)->toBeFalse();
});
