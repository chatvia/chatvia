<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Calendar;

use App\Mcp\McpCallerContext;
use Domain\Agent\Models\Agent;
use Domain\Booking\Contracts\Scheduler;
use Domain\Booking\Data\MeetingTypeData;
use Domain\Booking\Exceptions\BookingException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Meeting discovery: the model asks which meetings the workspace offers, then
 * shows one with render-calendar. The list comes from the workspace's
 * Calendly (its active event types).
 */
#[Name('find-meeting-types')]
#[Description('List the meetings a visitor can book (e.g. a demo, a consultation, an intro call), each with an `id`, name, duration in minutes and description. Call this the moment a visitor wants to book or schedule a meeting, then call render-calendar with the `id` of the one that fits.')]
#[IsReadOnly]
class FindMeetingTypesTool extends Tool
{
    public function handle(Request $request, McpCallerContext $caller, Scheduler $scheduler): Response|ResponseFactory
    {
        if ($caller->agentExternalId === null) {
            return Response::error('Missing X-Chatvia-Agent-Id identity header.');
        }

        if (! $caller->agent instanceof Agent) {
            return Response::error("Unknown agent \"{$caller->agentExternalId}\".");
        }

        try {
            $types = $scheduler->meetingTypes($caller->agent);
        } catch (BookingException) {
            return Response::error('The calendar is not available right now. Help the visitor another way (for example, offer to take their contact details) and do not promise a booking.');
        }

        if ($types === []) {
            return Response::make(Response::text(
                'There are no meetings to book right now. Help the visitor another way and do not promise a booking.'
            ))->withStructuredContent(['meeting_types' => []]);
        }

        return Response::make(Response::text(
            'Meeting types found. Call render-calendar with the `id` of the one that fits what the visitor wants.'
        ))->withStructuredContent([
            'meeting_types' => array_map(static fn (MeetingTypeData $type): array => [
                'id' => $type->uri,
                'name' => $type->name,
                'duration_minutes' => $type->durationMinutes,
                'description' => $type->description,
            ], $types),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
