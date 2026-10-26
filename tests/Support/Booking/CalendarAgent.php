<?php

declare(strict_types=1);

namespace Tests\Support\Booking;

use Domain\Agent\Models\Agent;
use Domain\Booking\Actions\EnsureBookingFormAction;
use Domain\Booking\Services\BookingInstanceMeetingType;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * An agent with its "Meeting bookings" form and a visitor's conversation, as
 * the calendar tools, actions and endpoints see them, plus a pending booking
 * instance for the fake scheduler's meeting type.
 */
final class CalendarAgent
{
    /** @return array{agent: Agent, conversation: Conversation, form: FormDefinition} */
    public static function seed(ConversationSource $source = ConversationSource::Widget, ?Agent $agent = null): array
    {
        $agent ??= Agent::factory()->create();
        $form = app(EnsureBookingFormAction::class)->execute($agent);
        $session = Session::factory()->create(['agent_id' => $agent->id]);
        $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $session->id, 'source' => $source]);

        return ['agent' => $agent, 'conversation' => $conversation, 'form' => $form];
    }

    public static function pendingInstance(FormDefinition $form, Conversation $conversation, string $eventType = FakeScheduler::INTRO_CALL): FormSubmission
    {
        return FormSubmission::factory()->forDefinition($form)->create([
            'chat_conversation_id' => $conversation->id,
            'status' => FormSubmissionStatus::Pending,
            'values' => [BookingInstanceMeetingType::KEY => $eventType],
            'expires_at' => now()->addHours(6),
        ]);
    }
}
