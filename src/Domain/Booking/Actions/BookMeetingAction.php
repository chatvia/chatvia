<?php

declare(strict_types=1);

namespace Domain\Booking\Actions;

use Domain\Booking\Contracts\Scheduler;
use Domain\Booking\Data\BookedMeetingData;
use Domain\Booking\Data\BookMeetingData;
use Domain\Booking\Data\BookMeetingResultData;
use Domain\Booking\Exceptions\BookingException;
use Domain\Booking\Services\BookingInstanceMeetingType;
use Domain\Conversation\Models\Message;
use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Facades\DB;

/**
 * Book the meeting a visitor picked in a shown calendar, in one transaction:
 *   1. claim the booking instance (row lock, once; an expired one is refused),
 *   2. book it with the scheduler,
 *   3. store the booking as a lead on the agent's "Meeting bookings" form,
 *   4. flip the message's calendar artifact to booked for reload state,
 *   5. fire {@see FormSubmitted} (CRM webhook, lead notification).
 *
 * The scheduler call runs while the row is locked, so two clicks cannot book
 * twice. When the scheduler refuses, the transaction rolls back and the
 * calendar stays bookable. The binding (agent + conversation) is checked by
 * the calling controller before this runs.
 */
class BookMeetingAction
{
    /** The capture path, as the CRM payload names it. */
    private const string SOURCE = 'calendar';

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly BookingInstanceMeetingType $meetingType,
    ) {}

    /**
     * @throws FormNotClaimableException when the calendar was booked or has expired
     * @throws BookingException
     */
    public function execute(BookMeetingData $data): BookMeetingResultData
    {
        $definition = $data->submission->definition;

        return DB::transaction(function () use ($data, $definition): BookMeetingResultData {
            $claimed = $data->submission->claimPending();
            $type = $this->meetingType->of($claimed);

            $booked = $this->scheduler->book($claimed->agent, $type, $data->request);

            $values = array_filter([
                'name' => $data->request->name,
                'email' => $data->request->email,
                'meeting_type' => $type->name,
                'start_time' => $booked->startTime->toIso8601ZuluString(),
                'timezone' => $data->request->timezone,
                'notes' => $data->request->notes,
            ], filled(...));

            // Only a message of the instance's own conversation is linked or written.
            $message = $data->assistantMessageExternalId !== null
                ? $data->conversation->messages()->where('external_id', $data->assistantMessageExternalId)->first()
                : null;

            $claimed->forceFill([
                'status' => FormSubmissionStatus::Submitted,
                'values' => $values,
                'values_checksum' => FormSubmission::checksum($values),
                'ip_address' => $data->ip,
                'user_agent' => $data->userAgent,
                'chat_message_id' => $message?->id,
                'submitted_at' => now(),
            ])->save();

            $this->markArtifactBooked($message, $claimed->external_id, $booked);

            FormSubmitted::dispatch(new CapturedSubmissionData(
                agentId: $claimed->agent_id,
                workspaceId: $claimed->workspace_id,
                submissionId: $claimed->id,
                formSlug: $definition->slug,
                formType: $definition->form_type,
                conversationExternalId: $data->conversation->external_id,
                created: true,
                source: self::SOURCE,
                values: $values,
            ));

            return new BookMeetingResultData(
                status: FormSubmissionStatus::Submitted,
                startTime: $booked->startTime,
                message: (string) $definition->success_message,
            );
        });
    }

    /**
     * Flip the calendar artifact on the owning message so a reload shows the
     * booked time. Only a message that carries this booking instance is
     * written; the visitor's name and email stay on the lead row only.
     */
    private function markArtifactBooked(?Message $message, string $bookingInstanceId, BookedMeetingData $booked): void
    {
        if (! $message instanceof Message) {
            return;
        }

        $metadata = $message->metadata ?? [];
        $artifacts = $metadata['artifacts'] ?? [];

        $index = array_find_key($artifacts, fn (mixed $artifact): bool => ($artifact['kind'] ?? null) === 'calendar'
            && ($artifact['data']['booking_instance_id'] ?? null) === $bookingInstanceId);

        if ($index === null) {
            return;
        }

        $artifacts[$index]['data']['status'] = FormSubmissionStatus::Submitted->value;
        $artifacts[$index]['data']['booked_start_time'] = $booked->startTime->toIso8601ZuluString();
        $metadata['artifacts'] = $artifacts;
        $message->metadata = $metadata;
        $message->save();
    }
}
