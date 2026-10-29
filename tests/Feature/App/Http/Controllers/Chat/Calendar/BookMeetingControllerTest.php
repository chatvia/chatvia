<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * POST chat/{agentSlug}/calendar/{bookingInstance}/book books the time the
 * visitor picked in a calendar the agent showed in the hosted chat. The
 * calendar must be in the visitor's own session, and books once.
 */
beforeEach(function (): void {
    withLicense();
    $this->travelTo(CarbonImmutable::create(2026, 11, 2, 9, 0, 0));
    Event::fake([FormSubmitted::class]);
    $this->scheduler = FakeScheduler::install();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->base = "/chat/{$this->agent->slug}";
    $conversationId = HostedChatVisitor::startConversation($this->agent, 'I want to book a call');
    $conversation = Conversation::query()->where('external_id', $conversationId)->firstOrFail();
    ['form' => $form] = CalendarAgent::seed(agent: $this->agent);
    $this->instance = CalendarAgent::pendingInstance($form, $conversation);
});

function cbmBook(string $base, FormSubmission $instance, array $overrides = []): TestResponse
{
    return test()->postJson("{$base}/calendar/{$instance->external_id}/book", [
        'start_time' => '2026-11-03T10:00:00Z',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'timezone' => 'Europe/London',
        ...$overrides,
    ]);
}

it('books the picked time once and answers 409 to a second booking', function (): void {
    cbmBook($this->base, $this->instance)
        ->assertOk()
        ->assertJsonPath('status', 'submitted')
        ->assertJsonPath('start_time', '2026-11-03T10:00:00Z');

    cbmBook($this->base, $this->instance)->assertConflict()->assertJsonPath('status', 'submitted');

    expect($this->instance->fresh()->status)->toBe(FormSubmissionStatus::Submitted);
});

it('answers 404 for a calendar in another visitor\'s session', function (): void {
    $this->flushSession();
    HostedChatVisitor::startConversation($this->agent, 'Hello');

    cbmBook($this->base, $this->instance)->assertNotFound();
    expect($this->scheduler->booked)->toBe([]);
});
