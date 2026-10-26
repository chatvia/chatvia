<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\WidgetHeaders;
use Illuminate\Support\Arr;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * GET /widget/calendar/{bookingInstance}/slots returns the free times of the
 * week the visitor browses to, for a calendar in the caller's conversation.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 11, 2, 9, 0, 0));
    $this->scheduler = FakeScheduler::install();
    $this->scheduler->slots = [CarbonImmutable::parse('2026-11-10T10:00:00Z')];

    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
    $this->headers = EmbeddedWidget::chatHeaders($this->agent);
    ['form' => $form] = CalendarAgent::seed(agent: $this->agent);
    $this->instance = CalendarAgent::pendingInstance(
        $form,
        Conversation::query()->where('external_id', $this->headers[WidgetHeaders::CONVERSATION_ID])->firstOrFail(),
    );
    $this->url = "/widget/calendar/{$this->instance->external_id}/slots?".Arr::query(['from' => '2026-11-09T09:00:00Z', 'to' => '2026-11-16T09:00:00Z']);
});

it('lists the free times of the week', function (): void {
    $this->withHeaders($this->headers)->getJson($this->url)
        ->assertOk()
        ->assertExactJson(['slots' => [['start_time' => '2026-11-10T10:00:00Z']]]);
});

it('refuses a caller outside the calendar\'s conversation', function (): void {
    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))->getJson($this->url)->assertForbidden();
});
