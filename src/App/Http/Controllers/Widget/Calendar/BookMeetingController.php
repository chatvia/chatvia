<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget\Calendar;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Chat\Calendar\BookMeetingRequest;
use App\Http\Responses\CalendarBookingResponses;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Booking\Actions\BookMeetingAction;
use Domain\Booking\Actions\EnsureBookingFormAction;
use Domain\Booking\Data\BookMeetingData;
use Domain\Booking\Exceptions\BookingException;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Http\JsonResponse;

/**
 * Widget booking: book the time the visitor picked in a shown calendar. The
 * calendar's conversation must be in the caller's widget session with this
 * agent (and, on a protected agent, the signed-in chatter's), so a visitor
 * cannot book into someone else's thread.
 */
class BookMeetingController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        BookMeetingRequest $request,
        #[WidgetAgent] Agent $agent,
        FormSubmission $bookingInstance,
        BookMeetingAction $action,
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
