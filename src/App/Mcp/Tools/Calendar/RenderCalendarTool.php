<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Calendar;

use App\Mcp\McpCallerContext;
use Domain\Agent\Models\Agent;
use Domain\Booking\Actions\RenderBookingCalendarAction;
use Domain\Booking\Data\CalendarArtifactData;
use Domain\Booking\Data\RenderCalendarOutcomeData;
use Domain\Booking\Enums\BookingFailure;
use Domain\Booking\Exceptions\BookingException;
use Domain\Conversation\Models\Conversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

/**
 * Shows a booking calendar inline in the chat. The tool checks who is asking
 * and where, hands the request to {@see RenderBookingCalendarAction}, and
 * returns the result as a `calendar` artifact the chat UI draws (or as
 * guidance for the model, such as a booking link on a messaging channel).
 */
#[Name('render-calendar')]
#[Description('Show the visitor a booking calendar for one meeting type, inline in the chat. The visitor picks a free time and books it there; the calendar books the meeting itself. Pass the exact `id` find-meeting-types returned; never invent one.')]
#[IsOpenWorld]
class RenderCalendarTool extends Tool
{
    public function handle(Request $request, McpCallerContext $caller, RenderBookingCalendarAction $render): Response|ResponseFactory
    {
        if ($caller->agentExternalId === null) {
            return Response::error('Missing X-Chatvia-Agent-Id identity header.');
        }

        if ($caller->conversationExternalId === null) {
            return Response::error('Missing X-Chatvia-Conversation-Id header. A calendar can only be shown inside an active chat conversation.');
        }

        if (! $caller->agent instanceof Agent) {
            return Response::error("Unknown agent \"{$caller->agentExternalId}\".");
        }

        if (! $caller->conversation instanceof Conversation) {
            return Response::error('Conversation not found or does not belong to this agent.');
        }

        $validated = $request->validate([
            'meeting_type' => ['required', 'string', 'max:300'],
        ]);

        try {
            $outcome = $render->execute($caller->agent, $caller->conversation, Str::trim($validated['meeting_type']));
        } catch (BookingException $e) {
            if ($e->reason !== BookingFailure::NotConnected) {
                report($e);
            }

            return Response::error($e->reason === BookingFailure::NotConnected
                ? 'Booking is not set up right now. Help the visitor another way (for example, offer to take their contact details) and do not promise a booking.'
                : 'The calendar could not be reached. Tell the visitor to try again in a moment, and do not promise a booking.');
        }

        return $this->respond($outcome, $validated['meeting_type']);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'meeting_type' => $schema->string()
                ->description('The exact `id` of the meeting type, as returned by find-meeting-types.')
                ->required(),
        ];
    }

    private function respond(RenderCalendarOutcomeData $outcome, string $requested): Response|ResponseFactory
    {
        if ($outcome->calendar instanceof CalendarArtifactData) {
            return $this->rendered($outcome->calendar);
        }

        if ($outcome->alreadyBooked) {
            $when = $outcome->bookedStartTime !== null ? " (starting {$outcome->bookedStartTime} UTC)" : '';

            return Response::make(Response::text(
                "The visitor already booked a meeting{$when}. Do not show the calendar again: confirm their booking and keep helping."
            ));
        }

        if ($outcome->bookingUrl !== null) {
            return Response::make(Response::text(
                "This meeting is booked on its own page, not in the chat. Share this booking link with the visitor: {$outcome->bookingUrl}"
            ));
        }

        if ($outcome->meetingTypeNames === []) {
            return Response::make(Response::text(
                'There are no meetings to book right now. Help the visitor another way and do not promise a booking.'
            ));
        }

        return Response::error("No meeting type matches \"{$requested}\". Call find-meeting-types, then render-calendar with one of its exact ids (".implode(', ', $outcome->meetingTypeNames).').');
    }

    private function rendered(CalendarArtifactData $calendar): ResponseFactory
    {
        $empty = $calendar->slots === []
            ? ' There are no free times in the next 7 days; the visitor can browse later weeks in the calendar.'
            : '';

        return Response::make(Response::text(
            "A booking calendar for \"{$calendar->eventTypeName}\" ({$calendar->durationMinutes} minutes) is now shown to the visitor.{$empty} Briefly invite them to pick a time. Do NOT list times, do not say where on screen it appears, and do not ask for their name, email or a time in text: the calendar books the meeting itself."
        ))->withStructuredContent(['calendar' => $calendar->toArray()]);
    }
}
