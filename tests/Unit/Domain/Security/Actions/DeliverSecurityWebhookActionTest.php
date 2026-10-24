<?php

use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\DeliverSecurityWebhookAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\WebhookSignature;

/**
 * SIEM webhook delivery: whether the action sends, with the policy's URL,
 * secret and the event name, the failure counter reset on success and kept
 * on a refused URL. Signing, redirects and the SSRF list are
 * SignedWebhookSender's and are tested there; queueing is the listener's
 * (SendSecurityEventToSiemTest).
 */

/** A security event of the workspace that the SIEM gets. */
function dswEvent(int $workspaceId): SecurityEventOccurred
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
function dswEnableSiem(User $owner, array $overrides = []): WorkspaceSecurityPolicy
{
    $policy = WorkspaceSecurityPolicy::forWorkspace($owner->workspace()->id);
    $policy->update(array_merge([
        'is_webhook_enabled' => true,
        'webhook_url' => 'https://siem.example.com/ingest',
        'webhook_secret' => 'shh-secret',
    ], $overrides));

    return $policy;
}

beforeEach(fn () => withEnterpriseLicense());

it('delivers to the policy URL with its secret and resets the failure counter', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $owner = User::factory()->create();
    $policy = dswEnableSiem($owner, ['webhook_failures' => 4]);

    app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://siem.example.com/ingest'
        && $request->header('X-Chatvia-Signature')[0] === 'sha256='.WebhookSignature::sign($request->body(), 'shh-secret')
        && $request->header('X-Chatvia-Event')[0] === SecurityAuditAction::PolicyChanged->value);

    expect($policy->fresh()->webhook_failures)->toBe(0)
        ->and($policy->fresh()->webhook_last_delivered_at)->not->toBeNull();
});

it('sends the same delivery id when the queue retries the event', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $owner = User::factory()->create();
    dswEnableSiem($owner);
    $event = dswEvent($owner->workspace()->id);

    // A retry runs the delivery again on the event as the queue stored it.
    app(DeliverSecurityWebhookAction::class)->execute($event);
    app(DeliverSecurityWebhookAction::class)->execute(unserialize(serialize($event)));

    $ids = Http::recorded()->map(fn (array $pair): mixed => $pair[0]->data()['delivery_id']);

    expect($ids->all())->toBe([$event->deliveryId, $event->deliveryId]);
});

it('skips the policy write after a success when nothing changed within the minute', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $owner = User::factory()->create();
    $this->travelTo(now()->startOfMinute());
    $policy = dswEnableSiem($owner, ['webhook_failures' => 0, 'webhook_last_delivered_at' => now()->subSeconds(10)]);
    $stamped = $policy->fresh()->webhook_last_delivered_at;

    app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id));

    expect($policy->fresh()->webhook_last_delivered_at?->equalTo($stamped))->toBeTrue();

    $this->travel(2)->minutes();
    app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id));

    expect($policy->fresh()->webhook_last_delivered_at?->greaterThan($stamped))->toBeTrue();
});

it('re-checks the stored URL at send time and refuses an internal target', function (): void {
    // A URL saved while it looked public must still be refused when it now
    // points inside, and the refusal does not count as a delivery.
    app()['env'] = 'production';
    Http::fake();
    $owner = User::factory()->create();
    $policy = dswEnableSiem($owner, ['webhook_url' => 'http://169.254.169.254/latest/meta-data', 'webhook_failures' => 1]);

    expect(fn () => app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id)))
        ->toThrow(UnsafeUrlException::class);

    Http::assertNothingSent();
    expect($policy->fresh()->webhook_failures)->toBe(1)
        ->and($policy->fresh()->webhook_last_delivered_at)->toBeNull();
});

it('does not deliver when the webhook is disabled', function (): void {
    Http::fake();
    $owner = User::factory()->create();
    dswEnableSiem($owner, ['is_webhook_enabled' => false]);

    app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id));

    Http::assertNothingSent();
});

it('respects the per-event allowlist', function (): void {
    Http::fake();
    $owner = User::factory()->create();
    dswEnableSiem($owner, ['webhook_events' => [SecurityAuditAction::PolicyRetentionChanged->value]]);

    app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id)); // PolicyChanged — not allowlisted

    Http::assertNothingSent();
});

it('throws on a non-2xx response so the queue retries', function (): void {
    Http::fake(['*' => Http::response('nope', 500)]);
    $owner = User::factory()->create();
    dswEnableSiem($owner);

    expect(fn () => app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id)))
        ->toThrow(RequestException::class);
});

it('sends nothing without an Enterprise license and keeps the webhook for later', function (): void {
    Http::fake();
    $owner = User::factory()->create();
    $policy = dswEnableSiem($owner);
    saveSettings(BrandSettings::class, ['license_key' => null]);

    expect(app(DeliverSecurityWebhookAction::class)->wants(dswEvent($owner->workspace()->id)))->toBeFalse();

    app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id));

    Http::assertNothingSent();
    expect($policy->fresh()->is_webhook_enabled)->toBeTrue()
        ->and($policy->fresh()->webhook_url)->toBe('https://siem.example.com/ingest');
});
