<?php

declare(strict_types=1);

namespace Domain\Booking\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Booking\Contracts\Scheduler;
use Domain\Booking\Data\BookedMeetingData;
use Domain\Booking\Data\BookingRequestData;
use Domain\Booking\Data\MeetingTypeData;
use Domain\Booking\Exceptions\BookingException;
use Domain\Integration\Mcp\Data\McpToolResultData;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * {@see Scheduler} on Calendly, through the tools of its hosted MCP server on
 * the workspace's Calendly connection. The connection's token is scoped to
 * that server, so the REST API is not used. The replies follow Calendly's API
 * (`resource`, `collection`). Meeting types are cached briefly per connection:
 * a calendar render and its booking read them a few seconds apart.
 */
class CalendlyScheduler implements Scheduler
{
    private const int MEETING_TYPES_TTL_MINUTES = 10;

    /** Error text that means the account may not book through the API (a free plan). */
    private const array NOT_ALLOWED_HINTS = ['403', 'forbidden', 'permission', 'paid plan', 'upgrade'];

    /** Error text that means the time is no longer free. */
    private const array SLOT_TAKEN_HINTS = ['no longer available', 'not available', 'already booked', 'conflict', '409'];

    public function __construct(
        private readonly McpClient $client,
        private readonly CalendlyConnection $connection,
    ) {}

    public function meetingTypes(Agent $agent): array
    {
        $server = $this->server($agent);

        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            "booking:calendly:{$server->id}:meeting-types",
            now()->addMinutes(self::MEETING_TYPES_TTL_MINUTES),
            function () use ($server): array {
                $user = $this->read($server, 'users-get_current_user', [])['resource']['uri'] ?? null;
                $body = $this->read($server, 'event_types-list_event_types', array_filter([
                    'user' => $user,
                    'active' => true,
                    'count' => 100,
                ], fn (mixed $value): bool => $value !== null));

                return array_values(array_filter((array) ($body['collection'] ?? []), is_array(...)));
            },
        );

        return collect($rows)
            ->filter(fn (array $row): bool => ($row['active'] ?? true) === true && ($row['secret'] ?? false) !== true)
            ->map(MeetingTypeData::fromCalendly(...))
            ->filter(fn (MeetingTypeData $type): bool => $type->uri !== '' && $type->name !== '')
            ->values()
            ->all();
    }

    public function availableTimes(Agent $agent, MeetingTypeData $type, CarbonInterface $from, CarbonInterface $to): array
    {
        $body = $this->read($this->server($agent), 'event_types-list_event_type_available_times', [
            'event_type' => $type->uri,
            'start_time' => $from->toImmutable()->utc()->toIso8601ZuluString(),
            'end_time' => $to->toImmutable()->utc()->toIso8601ZuluString(),
        ]);

        return collect((array) ($body['collection'] ?? []))
            ->filter(fn (mixed $slot): bool => is_array($slot)
                && ($slot['status'] ?? 'available') === 'available'
                && filled($slot['start_time'] ?? null))
            ->map(fn (array $slot): CarbonImmutable => CarbonImmutable::parse((string) $slot['start_time'])->utc())
            ->sort()
            ->values()
            ->all();
    }

    public function book(Agent $agent, MeetingTypeData $type, BookingRequestData $request): BookedMeetingData
    {
        $booking = array_filter([
            'event_type' => $type->uri,
            'start_time' => $request->startTime->utc()->toIso8601ZuluString(),
            'invitee' => [
                'name' => $request->name,
                'email' => $request->email,
                'timezone' => $request->timezone,
            ],
            'location' => $type->location,
            'questions_and_answers' => $type->notesQuestion !== null && filled($request->notes)
                ? [[...$type->notesQuestion, 'answer' => $request->notes]]
                : null,
        ], fn (mixed $value): bool => $value !== null);

        $result = $this->call($this->server($agent), 'meetings-create_invitee', ['post_invitee_request' => $booking]);

        if ($result->isError) {
            throw $this->bookingFailure($result->text, $type);
        }

        $resource = (array) ($this->body($result)['resource'] ?? []);

        return new BookedMeetingData(
            startTime: $request->startTime->utc(),
            eventUri: filled($resource['event'] ?? null) ? (string) $resource['event'] : null,
            inviteeUri: filled($resource['uri'] ?? null) ? (string) $resource['uri'] : null,
        );
    }

    private function server(Agent $agent): AgentMcpServer
    {
        return $this->connection->serverFor($agent) ?? throw BookingException::notConnected();
    }

    /**
     * A read whose failure only means "try again later".
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function read(AgentMcpServer $server, string $tool, array $arguments): array
    {
        $result = $this->call($server, $tool, $arguments);

        if ($result->isError) {
            throw BookingException::unavailable(new McpException("Calendly {$tool} failed: ".Str::limit($result->text, 300)));
        }

        return $this->body($result);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function call(AgentMcpServer $server, string $tool, array $arguments): McpToolResultData
    {
        try {
            return $this->client->callTool($server, $tool, $arguments);
        } catch (McpException $e) {
            throw BookingException::unavailable($e);
        }
    }

    /**
     * The reply as data: the structured content, or the JSON text Calendly
     * also sends.
     *
     * @return array<string, mixed>
     */
    private function body(McpToolResultData $result): array
    {
        if ($result->structuredContent !== null) {
            return $result->structuredContent;
        }

        $decoded = json_decode($result->text, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Calendly reports a failed booking only as text, so the reason is read
     * from it. Anything unrecognised is "try again", never a false "taken".
     */
    private function bookingFailure(string $text, MeetingTypeData $type): BookingException
    {
        $lower = Str::lower($text);

        if (Str::contains($lower, self::NOT_ALLOWED_HINTS)) {
            return BookingException::bookingNotAllowed($type->schedulingUrl);
        }

        if (Str::contains($lower, self::SLOT_TAKEN_HINTS)) {
            return BookingException::slotTaken();
        }

        return BookingException::unavailable(new McpException('Calendly booking failed: '.Str::limit($text, 300)));
    }
}
