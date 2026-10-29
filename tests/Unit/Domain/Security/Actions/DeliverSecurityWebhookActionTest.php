<?php

use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\DeliverSecurityWebhookAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Domain\Security\Services\SiemWebhookPolicy;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\WebhookSignature;

/**
 * SIEM webhook delivery: whether the action sends, with the policy's URL,
 * secret and the event name, the failure counter reset on success and kept
 * on a refused URL. Signing, redirects and the SSRF list are
 * SignedWebhookSender's and are tested there.
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

it('delivers to the policy URL with its secret, the same delivery id on a retry, and resets the failure counter', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $owner = User::factory()->create();
    $policy = dswEnableSiem($owner, ['webhook_failures' => 4]);
    $event = dswEvent($owner->workspace()->id);

    app(DeliverSecurityWebhookAction::class)->execute($event);
    app(DeliverSecurityWebhookAction::class)->execute(unserialize(serialize($event)));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://siem.example.com/ingest'
        && $request->header('X-Chatvia-Signature')[0] === 'sha256='.WebhookSignature::sign($request->body(), 'shh-secret')
        && $request->header('X-Chatvia-Event')[0] === SecurityAuditAction::PolicyChanged->value);
    expect(Http::recorded()->map(fn (array $pair): mixed => $pair[0]->data()['delivery_id'])->all())->toBe([$event->deliveryId, $event->deliveryId])
        ->and($policy->fresh()->webhook_failures)->toBe(0)
        ->and($policy->fresh()->webhook_last_delivered_at)->not->toBeNull();
});

it('sends nothing while disabled, for an event off the allowlist or without an Enterprise license, and keeps the webhook', function (array $overrides, bool $licensed): void {
    Http::fake();
    $owner = User::factory()->create();
    $policy = dswEnableSiem($owner, $overrides);
    if (! $licensed) {
        saveSettings(BrandSettings::class, ['license_key' => null]);
    }

    expect(app(SiemWebhookPolicy::class)->accepts(dswEvent($owner->workspace()->id)))->toBeFalse();

    app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id));

    Http::assertNothingSent();
    expect($policy->fresh()->webhook_url)->toBe('https://siem.example.com/ingest');
})->with([
    'the webhook disabled' => [['is_webhook_enabled' => false], true],
    'an event off the allowlist' => [['webhook_events' => [SecurityAuditAction::PolicyRetentionChanged->value]], true],
    'no Enterprise license' => [[], false],
]);

it('refuses an internal target at send time, and throws on a non-2xx so the queue retries, counting neither as delivered', function (string $url, Closure $arrange, string $exception, int $sent): void {
    $arrange();
    $owner = User::factory()->create();
    $policy = dswEnableSiem($owner, ['webhook_url' => $url, 'webhook_failures' => 1]);

    expect(fn () => app(DeliverSecurityWebhookAction::class)->execute(dswEvent($owner->workspace()->id)))
        ->toThrow($exception);

    Http::assertSentCount($sent);
    expect($policy->fresh()->webhook_failures)->toBe(1)
        ->and($policy->fresh()->webhook_last_delivered_at)->toBeNull();
})->with([
    'an internal target, checked at send time outside testing' => ['http://169.254.169.254/latest/meta-data', function (): void {
        app()['env'] = 'production';
        Http::fake();
    }, UnsafeUrlException::class, 0],
    'a non-2xx response' => ['https://siem.example.com/ingest', fn () => Http::fake(['*' => Http::response('nope', 500)]), RequestException::class, 1],
]);
