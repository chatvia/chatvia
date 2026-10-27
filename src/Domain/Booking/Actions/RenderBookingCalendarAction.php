<?php

declare(strict_types=1);

namespace Domain\Booking\Actions;

use Domain\Agent\Models\Agent;
use Domain\Booking\Contracts\Schedulers;
use Domain\Booking\Data\CalendarArtifactData;
use Domain\Booking\Data\MeetingTypeData;
use Domain\Booking\Data\RenderCalendarOutcomeData;
use Domain\Booking\Exceptions\BookingException;
use Domain\Booking\Services\BookingInstanceMeetingType;
use Domain\Booking\Services\BookingWindow;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Show a booking calendar for one of the workspace's meeting types inside a
 * conversation. It resolves the type the model asked for against the real
 * ones, refuses when this visitor's session already booked, and otherwise
 * issues a booking instance: a pending submission of the agent's "Meeting
 * bookings" form that {@see BookMeetingAction} later claims. The pending row
 * keeps the meeting type and the scheduler it came from (the agent's Calendly
 * or Cal.com), and the first week of free times goes with it. On a
 * messaging channel the visitor gets the type's booking page instead.
 */
class RenderBookingCalendarAction
{
    /** How long a shown calendar stays bookable. */
    private const int TTL_HOURS = 6;

    public function __construct(
        private readonly Schedulers $schedulers,
        private readonly EnsureBookingFormAction $ensureForm,
    ) {}

    /**
     * @param  string  $requestedType  the meeting type's id (URI) — or, tolerated, its name
     *
     * @throws BookingException
     */
    public function execute(Agent $agent, Conversation $conversation, string $requestedType): RenderCalendarOutcomeData
    {
        $form = $this->ensureForm->execute($agent);

        $booked = $this->bookedInSession($conversation, $form);
        if ($booked instanceof FormSubmission) {
            return RenderCalendarOutcomeData::alreadyBooked($booked->field('start_time'));
        }

        $provider = $this->schedulers->providerFor($agent);
        $scheduler = $this->schedulers->using($provider);
        $types = $scheduler->meetingTypes($agent);
        $type = $this->match($types, $requestedType);

        if (! $type instanceof MeetingTypeData) {
            return RenderCalendarOutcomeData::notFound(array_map(static fn (MeetingTypeData $type): string => $type->name, $types));
        }

        // A messaging channel cannot draw the calendar, and some types need
        // answers the calendar does not ask for: both book on the type's page.
        if (! $type->bookableInChat || ! $conversation->source->isWeb()) {
            return RenderCalendarOutcomeData::linkOnly($type->schedulingUrl);
        }

        [$from, $to] = BookingWindow::firstWeek();
        $slots = $scheduler->availableTimes($agent, $type, $from, $to);

        $submission = FormSubmission::query()->create([
            'form_definition_id' => $form->id,
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
            'chat_conversation_id' => $conversation->id,
            'status' => FormSubmissionStatus::Pending,
            'values' => [BookingInstanceMeetingType::KEY => $type->uri, BookingInstanceMeetingType::PROVIDER_KEY => $provider],
            'expires_at' => now()->addHours(self::TTL_HOURS),
        ]);

        return RenderCalendarOutcomeData::rendered(new CalendarArtifactData(
            bookingInstanceId: $submission->external_id,
            eventTypeName: $type->name,
            durationMinutes: $type->durationMinutes,
            locationKind: $type->location['kind'] ?? null,
            slots: $slots,
            windowStart: $from,
            windowEnd: $to,
            maxDaysAhead: BookingWindow::MAX_DAYS_AHEAD,
            notesRequired: $type->notesRequired,
            status: FormSubmissionStatus::Pending,
            expiresAt: $submission->expires_at,
        ));
    }

    /**
     * Small models echo a type's name instead of its URI, so match tolerantly.
     *
     * @param  list<MeetingTypeData>  $types
     */
    private function match(array $types, string $requested): ?MeetingTypeData
    {
        $slug = Str::slug($requested);

        return collect($types)->first(static fn (MeetingTypeData $type): bool => $type->uri === $requested
            || Str::lower($type->name) === Str::lower($requested)
            || Str::slug($type->name) === $slug);
    }

    /** The visitor's identity is the session, so a booking from an earlier chat still counts. */
    private function bookedInSession(Conversation $conversation, FormDefinition $form): ?FormSubmission
    {
        if ($conversation->chat_session_id === null) {
            return null;
        }

        return $form->submissions()
            ->submitted()
            ->whereHas('conversation', fn (Builder $query): Builder => $query->where('chat_session_id', $conversation->chat_session_id))
            ->latest('id')
            ->first();
    }
}
