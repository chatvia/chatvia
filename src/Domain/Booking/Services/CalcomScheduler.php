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
use Domain\Integration\Mcp\Exceptions\McpException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * {@see Scheduler} on Cal.com, through the tools of
 * its hosted MCP server on the workspace's Cal.com connection. The tools wrap
 * Cal.com's API v2, so replies carry their payload under `data`; free times
 * come keyed by day. A meeting type's id is Cal.com's numeric event type id.
 */
class CalcomScheduler extends McpScheduler
{
    public const string CONNECTOR_SLUG = 'cal-com';

    /** Error text that means the account may not book this way. */
    private const array NOT_ALLOWED_HINTS = ['403', 'forbidden', 'permission', 'upgrade', 'paid plan'];

    /** Error text that means the time is no longer free. */
    private const array SLOT_TAKEN_HINTS = ['no available users', 'no_available_users', 'already has booking', 'already booked', 'not available', 'unavailable', '409'];

    public function meetingTypes(Agent $agent): array
    {
        $server = $this->server($agent);

        /** @var array{username: string|null, rows: list<array<string, mixed>>} $cached */
        $cached = Cache::remember(
            "booking:cal-com:{$server->id}:meeting-types",
            now()->addMinutes(self::MEETING_TYPES_TTL_MINUTES),
            function () use ($server): array {
                $me = $this->data($this->read($server, 'get_me', []));
                $types = $this->data($this->read($server, 'get_event_types', []));

                return [
                    'username' => filled($me['username'] ?? null) ? (string) $me['username'] : null,
                    'rows' => array_values(array_filter(array_is_list($types) ? $types : (array) ($types['eventTypes'] ?? []), is_array(...))),
                ];
            },
        );

        return collect($cached['rows'])
            ->filter(fn (array $row): bool => ($row['hidden'] ?? false) !== true)
            ->map(fn (array $row): MeetingTypeData => MeetingTypeData::fromCalcom($row, $cached['username']))
            ->filter(fn (MeetingTypeData $type): bool => $type->uri !== '' && $type->name !== '')
            ->values()
            ->all();
    }

    public function availableTimes(Agent $agent, MeetingTypeData $type, CarbonInterface $from, CarbonInterface $to): array
    {
        $days = $this->data($this->read($this->server($agent), 'get_availability', [
            'eventTypeId' => (int) $type->uri,
            'start' => $from->toImmutable()->utc()->toIso8601ZuluString(),
            'end' => $to->toImmutable()->utc()->toIso8601ZuluString(),
            'timeZone' => 'UTC',
        ]));

        return collect($days)
            ->flatten(1)
            ->map(fn (mixed $slot): mixed => is_array($slot) ? ($slot['start'] ?? $slot['time'] ?? null) : $slot)
            ->filter(filled(...))
            ->map(fn (mixed $start): CarbonImmutable => CarbonImmutable::parse((string) $start)->utc())
            ->sort()
            ->values()
            ->all();
    }

    public function book(Agent $agent, MeetingTypeData $type, BookingRequestData $request): BookedMeetingData
    {
        $booking = array_filter([
            'eventTypeId' => (int) $type->uri,
            'start' => $request->startTime->utc()->toIso8601ZuluString(),
            'attendee' => [
                'name' => $request->name,
                'email' => $request->email,
                'timeZone' => $request->timezone,
            ],
            'bookingFieldsResponses' => filled($request->notes) ? ['notes' => $request->notes] : null,
        ], fn (mixed $value): bool => $value !== null);

        $result = $this->call($this->server($agent), 'create_booking', $booking);

        if ($result->isError) {
            throw $this->bookingFailure($result->text, $type);
        }

        $data = $this->data($this->body($result));

        return new BookedMeetingData(
            startTime: $request->startTime->utc(),
            eventUri: filled($data['uid'] ?? null) ? (string) $data['uid'] : null,
            inviteeUri: null,
        );
    }

    #[\Override]
    protected function connectorSlug(): string
    {
        return self::CONNECTOR_SLUG;
    }

    /**
     * API v2 wraps a payload as `{status, data}`; a tool may also return it bare.
     *
     * @param  array<string, mixed>  $body
     * @return array<string|int, mixed>
     */
    private function data(array $body): array
    {
        return is_array($body['data'] ?? null) ? $body['data'] : $body;
    }

    /**
     * Cal.com reports a failed booking only as text, so the reason is read
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

        return BookingException::unavailable(new McpException('Cal.com booking failed: '.Str::limit($text, 300)));
    }
}
