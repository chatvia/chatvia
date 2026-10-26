<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget\Calendar;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Chat\Calendar\ListBookingSlotsRequest;
use App\Http\Responses\CalendarBookingResponses;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Booking\Actions\EnsureBookingFormAction;
use Domain\Booking\Actions\ListBookingSlotsAction;
use Domain\Booking\Exceptions\BookingException;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Http\JsonResponse;

/**
 * Widget booking calendar: the free times of the week the visitor browses
 * to. The calendar's conversation must be in the caller's widget session with
 * this agent (and, on a protected agent, the signed-in chatter's).
 */
class ListBookingSlotsController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        ListBookingSlotsRequest $request,
        #[WidgetAgent] Agent $agent,
        FormSubmission $bookingInstance,
        ListBookingSlotsAction $action,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse {
        abort_unless($bookingInstance->agent_id === $agent->id, JsonResponse::HTTP_NOT_FOUND);
        abort_unless($bookingInstance->definition?->managed_by === EnsureBookingFormAction::CAPABILITY_SLUG, JsonResponse::HTTP_NOT_FOUND);

        $conversation = $findConversation->execute(
            (string) $bookingInstance->conversation?->external_id,
            $this->widgetSessionId($request) ?? '',
            $agent,
            $this->widgetViewer($request),
        );
        abort_unless($conversation instanceof Conversation, JsonResponse::HTTP_FORBIDDEN);

        try {
            return CalendarBookingResponses::slots($action->execute($bookingInstance, $request->from(), $request->to()));
        } catch (FormNotClaimableException|BookingException $e) {
            return CalendarBookingResponses::failure($e);
        }
    }
}
