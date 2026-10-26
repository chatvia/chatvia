<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Illuminate\Support\Arr;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * GET chat/{agentSlug}/calendar/{bookingInstance}/slots returns the free
 * times of the week the visitor browses to, for a calendar in their session.
 */
beforeEach(function (): void {
    // Capabilities and forms need a license key; EnsureCapabilityLicenseTest covers the gate.
    withLicense();
    $this->travelTo(CarbonImmutable::create(2026, 11, 2, 9, 0, 0));
    $this->scheduler = FakeScheduler::install();
    $this->scheduler->slots = [CarbonImmutable::parse('2026-11-10T10:00:00Z')];

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->base = "/chat/{$this->agent->slug}";
    $conversationId = HostedChatVisitor::startConversation($this->agent, 'I want to book a call');
    ['form' => $form] = CalendarAgent::seed(agent: $this->agent);
    $this->instance = CalendarAgent::pendingInstance($form, Conversation::query()->where('external_id', $conversationId)->firstOrFail());
    $this->url = "{$this->base}/calendar/{$this->instance->external_id}/slots?".Arr::query(['from' => '2026-11-09T09:00:00Z', 'to' => '2026-11-16T09:00:00Z']);
});

it('lists the free times of the week', function (): void {
    $this->getJson($this->url)
        ->assertOk()
        ->assertExactJson(['slots' => [['start_time' => '2026-11-10T10:00:00Z']]]);
});

it('answers 409 for a booked calendar', function (): void {
    $this->instance->update(['status' => FormSubmissionStatus::Submitted]);

    $this->getJson($this->url)->assertConflict()->assertJsonPath('status', 'submitted');
});

it('needs a range', function (): void {
    $this->getJson("{$this->base}/calendar/{$this->instance->external_id}/slots")->assertUnprocessable();
});

it('answers 404 for a calendar in another visitor\'s session', function (): void {
    $this->flushSession();
    HostedChatVisitor::startConversation($this->agent, 'Hello');

    $this->getJson($this->url)->assertNotFound();
});
