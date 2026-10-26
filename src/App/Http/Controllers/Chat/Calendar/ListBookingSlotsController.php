<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Calendar;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\Calendar\ListBookingSlotsRequest;
use App\Http\Responses\CalendarBookingResponses;
use Domain\Agent\Models\Agent;
use Domain\Booking\Actions\EnsureBookingFormAction;
use Domain\Booking\Actions\ListBookingSlotsAction;
use Domain\Booking\Exceptions\BookingException;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Http\JsonResponse;

/**
 * Hosted Chat / Command Bar booking calendar: the free times of the week the
 * visitor browses to. The calendar must be the agent's and its conversation
 * in the visitor's own chat session.
 */
class ListBookingSlotsController
{
    public function __invoke(
        ListBookingSlotsRequest $request,
        FormSubmission $bookingInstance,
        #[HostedChatAgent] ?Agent $agent,
        ListBookingSlotsAction $action,
        HostedChatSession $session,
    ): JsonResponse {
        abort_unless($agent instanceof Agent && $bookingInstance->agent_id === $agent->id, 404);
        abort_unless($bookingInstance->definition?->managed_by === EnsureBookingFormAction::CAPABILITY_SLUG, 404);

        $conversation = $bookingInstance->conversation;
        abort_unless($conversation !== null && $conversation->session?->external_id === $session->find($agent), 404);

        try {
            return CalendarBookingResponses::slots($action->execute($bookingInstance, $request->from(), $request->to()));
        } catch (FormNotClaimableException|BookingException $e) {
            return CalendarBookingResponses::failure($e);
        }
    }
}
