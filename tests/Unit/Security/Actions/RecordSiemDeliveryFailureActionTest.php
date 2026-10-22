<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\RecordSiemDeliveryFailureAction;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config(['chatvia.siem.max_failures' => 3]);
    $this->workspace = User::factory()->create();
    $this->policy = WorkspaceSecurityPolicy::forWorkspace($this->workspace->workspace()->id);
    $this->policy->forceFill(['is_webhook_enabled' => true, 'webhook_failures' => 0])->save();
});

it('counts a failure and keeps the webhook on below the threshold', function (): void {
    Event::fake([SecurityEventOccurred::class]);

    app(RecordSiemDeliveryFailureAction::class)->execute($this->workspace->workspace()->id);

    $this->policy->refresh();
    expect($this->policy->webhook_failures)->toBe(1)
        ->and($this->policy->is_webhook_enabled)->toBeTrue();
    Event::assertNotDispatched(SecurityEventOccurred::class);
});

it('switches the webhook off at the threshold and audits it once', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $this->policy->forceFill(['webhook_failures' => 2])->save();

    app(RecordSiemDeliveryFailureAction::class)->execute($this->workspace->workspace()->id);
    app(RecordSiemDeliveryFailureAction::class)->execute($this->workspace->workspace()->id);

    $this->policy->refresh();
    expect($this->policy->webhook_failures)->toBe(4)
        ->and($this->policy->is_webhook_enabled)->toBeFalse();
    Event::assertDispatchedTimes(SecurityEventOccurred::class, 1);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === SecurityAuditAction::PolicyWebhookDisabled->value);
});

it('does nothing for a workspace without a policy', function (): void {
    // Count the whole table (the demo seed holds policies too), so the only
    // assertion is that this call created none.
    $before = WorkspaceSecurityPolicy::query()->count();

    app(RecordSiemDeliveryFailureAction::class)->execute(User::factory()->create()->workspace()->id);

    expect(WorkspaceSecurityPolicy::query()->count())->toBe($before);
});

it('audits the switch-off once when failures land together', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $this->policy->forceFill(['webhook_failures' => 2])->save();

    // The next three calls all start from a policy that was on; only the one
    // whose update turns it off may audit it.
    foreach (range(1, 3) as $ignored) {
        app(RecordSiemDeliveryFailureAction::class)->execute($this->workspace->workspace()->id);
    }

    expect($this->policy->fresh()->webhook_failures)->toBe(5);
    Event::assertDispatchedTimes(SecurityEventOccurred::class, 1);
    Event::assertDispatched(
        SecurityEventOccurred::class,
        fn (SecurityEventOccurred $event): bool => json_decode($event->metadata, true) === ['reason' => 'max_failures', 'failures' => 3],
    );
});

it('does not audit a webhook that was already off', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $this->policy->forceFill(['is_webhook_enabled' => false, 'webhook_failures' => 7])->save();

    app(RecordSiemDeliveryFailureAction::class)->execute($this->workspace->workspace()->id);

    expect($this->policy->fresh()->webhook_failures)->toBe(8);
    Event::assertNotDispatched(SecurityEventOccurred::class);
});
