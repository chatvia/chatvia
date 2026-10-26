<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Calendar;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\Calendar\BookMeetingRequest;
use App\Http\Responses\CalendarBookingResponses;
use Domain\Agent\Models\Agent;
use Domain\Booking\Actions\BookMeetingAction;
use Domain\Booking\Actions\EnsureBookingFormAction;
use Domain\Booking\Data\BookMeetingData;
use Domain\Booking\Exceptions\BookingException;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Http\JsonResponse;

/**
 * Hosted Chat / Command Bar booking: book the time the visitor picked in a
 * shown calendar. The calendar must be the agent's and its conversation in
 * the visitor's own chat session, so a replayed id cannot book for another
 * visitor.
 */
class BookMeetingController
{
    public function __invoke(
        BookMeetingRequest $request,
        FormSubmission $bookingInstance,
        #[HostedChatAgent] ?Agent $agent,
        BookMeetingAction $action,
        HostedChatSession $session,
    ): JsonResponse {
        abort_unless($agent instanceof Agent && $bookingInstance->agent_id === $agent->id, 404);
        abort_unless($bookingInstance->definition?->managed_by === EnsureBookingFormAction::CAPABILITY_SLUG, 404);

        $conversation = $bookingInstance->conversation;
        abort_unless($conversation !== null && $conversation->session?->external_id === $session->find($agent), 404);

        try {
            return CalendarBookingResponses::booked($action->execute(new BookMeetingData(
                submission: $bookingInstance,
                conversation: $conversation,
                request: $request->toData(),
                assistantMessageExternalId: $request->assistantMessageId(),
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            )));
        } catch (FormNotClaimableException|BookingException $e) {
            return CalendarBookingResponses::failure($e);
        }
    }
}
