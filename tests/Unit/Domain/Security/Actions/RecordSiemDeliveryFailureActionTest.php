<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\RecordSiemDeliveryFailureAction;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Event;

/**
 * A SIEM delivery that ran out of retries counts as a failure; at the
 * threshold the webhook is switched off and that is audited once, even when
 * failures land together.
 */
beforeEach(function (): void {
    config(['chatvia.siem.max_failures' => 3]);
    $this->owner = User::factory()->create();
    $this->policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id);
    $this->policy->forceFill(['is_webhook_enabled' => true, 'webhook_failures' => 0])->save();
});

it('switches the webhook off at the threshold and audits it once, even when failures land together', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $this->policy->forceFill(['webhook_failures' => 2])->save();

    foreach (range(1, 3) as $ignored) {
        app(RecordSiemDeliveryFailureAction::class)->execute($this->owner->workspace()->id);
    }

    expect($this->policy->fresh()->webhook_failures)->toBe(5)
        ->and($this->policy->fresh()->is_webhook_enabled)->toBeFalse();
    Event::assertDispatchedTimes(SecurityEventOccurred::class, 1);
    Event::assertDispatched(
        SecurityEventOccurred::class,
        fn (SecurityEventOccurred $event): bool => $event->action === SecurityAuditAction::PolicyWebhookDisabled->value
            && json_decode($event->metadata, true) === ['reason' => 'max_failures', 'failures' => 3],
    );
});

it('does not audit a webhook that was already off', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $this->policy->forceFill(['is_webhook_enabled' => false, 'webhook_failures' => 7])->save();

    app(RecordSiemDeliveryFailureAction::class)->execute($this->owner->workspace()->id);

    expect($this->policy->fresh()->webhook_failures)->toBe(8);
    Event::assertNotDispatched(SecurityEventOccurred::class);
});
