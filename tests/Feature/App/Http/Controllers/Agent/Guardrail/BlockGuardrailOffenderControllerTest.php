<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Notifications\ChatterBlockedNotification;
use Domain\Agent\Models\Agent;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Models\GuardrailEvent;
use Domain\Security\Models\BlockedVisitor;
use Domain\Security\Models\SecurityAuditLog;
use Illuminate\Support\Facades\Notification;

/**
 * From the Guardrails Activity log a teammate blocks the person behind an
 * abusive trigger: a visitor by fingerprint for a while (needs the Security
 * permission), a chatter by switching their account off and emailing them
 * (needs the People permission). A trigger no one can be blamed for cannot
 * be blocked. A visitor blocked here is unblocked from the Security page.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

function bgoUrl(Agent $agent, GuardrailEvent $event): string
{
    return route('agents.guardrails.block', ['agent' => $agent->external_id, 'event' => $event->external_id]);
}

it('blocks a visitor by fingerprint for the chosen time and a chatter by switching their account off, and the visitor can be unblocked', function (): void {
    $visitorEvent = GuardrailEvent::factory()->forAgent($this->agent)->create();
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $chatterEvent = GuardrailEvent::factory()->forAgent($this->agent)->create(['chatter_id' => $chatter->id, 'visitor_key' => null, 'user_name' => $chatter->name]);

    $this->actingAs($this->owner)
        ->from(route('agents.guardrails', $this->agent->external_id))
        ->post(bgoUrl($this->agent, $visitorEvent), ['duration' => 'day', 'reason' => 'Prompt hunting'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->actingAs($this->owner)
        ->from(route('agents.guardrails', $this->agent->external_id))
        ->post(bgoUrl($this->agent, $chatterEvent), ['reason' => 'Abuse', 'notify' => true])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(BlockedVisitor::query()->where('workspace_id', $this->agent->workspace_id)->sole())
        ->visitor_key->toBe($visitorEvent->visitor_key)
        ->reason->toBe('Prompt hunting')
        ->expires_at->isFuture()->toBeTrue()
        ->and($chatter->fresh()->isDeactivated())->toBeTrue();
    Notification::assertSentTo($chatter, ChatterBlockedNotification::class);

    $block = BlockedVisitor::query()->where('workspace_id', $this->agent->workspace_id)->sole();

    $this->deleteJson(route('security.blocked-visitors.destroy', $block->external_id))
        ->assertNoContent();

    expect(BlockedVisitor::query()->whereKey($block->id)->exists())->toBeFalse()
        ->and(SecurityAuditLog::query()->where('workspace_id', $this->agent->workspace_id)->where('action', 'visitor.unblocked')->sole()->target_id)->toBe($block->external_id);
});

it('needs the Security permission to block a visitor and the People one to block a chatter', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $visitorEvent = GuardrailEvent::factory()->forAgent($this->agent)->create();
    $chatterEvent = GuardrailEvent::factory()->forAgent($this->agent)->create(['chatter_id' => $chatter->id, 'visitor_key' => null]);

    $viewer = teammateWithPermissions($this->owner, ['agent.guardrails.view']);
    $security = teammateWithPermissions($this->owner, ['agent.guardrails.view', 'account.security']);

    $this->actingAs($viewer)->post(bgoUrl($this->agent, $visitorEvent), ['duration' => 'day'])->assertForbidden();
    $this->actingAs($viewer)->post(bgoUrl($this->agent, $chatterEvent))->assertForbidden();
    $this->actingAs($security)->post(bgoUrl($this->agent, $chatterEvent))->assertForbidden();
    $this->actingAs($security)->post(bgoUrl($this->agent, $visitorEvent), ['duration' => 'day'])->assertRedirect();

    expect(BlockedVisitor::query()->where('workspace_id', $this->agent->workspace_id)->count())->toBe(1)
        ->and($chatter->fresh()->isDeactivated())->toBeFalse();
});

it('refuses to block anyone for a trigger no one caused', function (array $attributes): void {
    $event = GuardrailEvent::factory()->forAgent($this->agent)->create($attributes);

    $this->actingAs($this->owner)
        ->post(bgoUrl($this->agent, $event), ['duration' => 'day'])
        ->assertSessionHasErrors('event');

    expect(BlockedVisitor::query()->where('workspace_id', $this->agent->workspace_id)->exists())->toBeFalse();
})->with([
    'personal data' => [['guard_type' => GuardType::Pii, 'outcome' => GuardOutcome::Redacted]],
    'a tool result' => [['stage' => GuardStage::Tool]],
    'no visitor or chatter' => [['visitor_key' => null]],
]);
