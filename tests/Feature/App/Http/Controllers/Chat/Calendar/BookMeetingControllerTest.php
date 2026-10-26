<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Booking\Exceptions\BookingException;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * POST chat/{agentSlug}/calendar/{bookingInstance}/book books the time the
 * visitor picked in a calendar the agent showed in the hosted chat. The
 * calendar must be the agent's, in the visitor's own session, and books once.
 * BookMeetingActionTest owns what is stored.
 */
beforeEach(function (): void {
    // Capabilities and forms need a license key; EnsureCapabilityLicenseTest covers the gate.
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

it('books the picked time', function (): void {
    cbmBook($this->base, $this->instance)
        ->assertOk()
        ->assertJsonPath('status', 'submitted')
        ->assertJsonPath('start_time', '2026-11-03T10:00:00Z');

    expect($this->instance->fresh()->status)->toBe(FormSubmissionStatus::Submitted);
});

it('answers 409 when the calendar already booked', function (): void {
    cbmBook($this->base, $this->instance)->assertOk();

    cbmBook($this->base, $this->instance)->assertConflict()->assertJsonPath('status', 'submitted');
});

it('answers 409 slot_taken when the time was taken', function (): void {
    $this->scheduler->failWith = BookingException::slotTaken();

    cbmBook($this->base, $this->instance)->assertConflict()->assertJsonPath('status', 'slot_taken');
});

it('answers 422 with the booking page when booking from the chat is not allowed', function (): void {
    $this->scheduler->failWith = BookingException::bookingNotAllowed('https://calendly.com/acme/intro');

    cbmBook($this->base, $this->instance)
        ->assertUnprocessable()
        ->assertJsonPath('status', 'booking_not_allowed')
        ->assertJsonPath('fallback_url', 'https://calendly.com/acme/intro');
});

it('answers 503 when the calendar cannot be reached', function (): void {
    $this->scheduler->failWith = BookingException::unavailable();

    cbmBook($this->base, $this->instance)->assertServiceUnavailable()->assertJsonPath('status', 'unavailable');
});

it('answers 410 for an expired calendar', function (): void {
    $this->instance->update(['expires_at' => now()->subMinute()]);

    cbmBook($this->base, $this->instance)->assertGone()->assertJsonPath('status', 'expired');
});

it('validates the visitor\'s details', function (): void {
    cbmBook($this->base, $this->instance, ['email' => 'not-an-email', 'timezone' => 'Mars/Base', 'start_time' => '2026-11-01T10:00:00Z'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'timezone', 'start_time']);

    expect($this->scheduler->booked)->toBe([]);
});

it('answers 404 for another agent\'s calendar', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);

    cbmBook("/chat/{$other->slug}", $this->instance)->assertNotFound();
});

it('answers 404 for a calendar in another visitor\'s session', function (): void {
    $this->flushSession();
    HostedChatVisitor::startConversation($this->agent, 'Hello');

    cbmBook($this->base, $this->instance)->assertNotFound();
    expect($this->scheduler->booked)->toBe([]);
});

it('answers 404 for an ordinary form instance', function (): void {
    $formInstance = FormSubmission::factory()
        ->forDefinition(FormDefinition::factory()->forAgent($this->agent)->create())
        ->create(['chat_conversation_id' => $this->instance->chat_conversation_id]);

    cbmBook($this->base, $formInstance)->assertNotFound();
});
