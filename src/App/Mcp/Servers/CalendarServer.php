<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Calendar\FindMeetingTypesTool;
use App\Mcp\Tools\Calendar\RenderCalendarTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * The Calendar capability connector: list the meetings a visitor can book
 * (find-meeting-types) and show a booking calendar inline in the chat
 * (render-calendar). Booking runs through the workspace's Calendly.
 */
#[Name('Calendar')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Let visitors book a meeting with a real calendar inline in the chat, instead of
agreeing a time in text. Two tools:
- find-meeting-types: the meetings that can be booked, each with an `id`, name,
  duration and description. Call it the moment a visitor wants a meeting, call,
  demo, consultation or appointment.
- render-calendar: show the visitor a calendar for one meeting type, by its `id`
  (from find-meeting-types). The visitor picks a free time and books it there; the
  calendar books the meeting itself.

Flow: when a visitor wants to meet, call find-meeting-types. If exactly one type fits,
call render-calendar with it; if several could fit, ask which one in one short
sentence, then call render-calendar. Never list free times, and never ask for a date,
time, name or email in text: the calendar does that. After rendering, briefly invite
the visitor to pick a time, without saying where on screen it appears. On messaging
channels render-calendar returns a booking link to share instead.
TXT)]
class CalendarServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    #[\Override]
    protected array $tools = [
        FindMeetingTypesTool::class,
        RenderCalendarTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    #[\Override]
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    #[\Override]
    protected array $prompts = [];
}
