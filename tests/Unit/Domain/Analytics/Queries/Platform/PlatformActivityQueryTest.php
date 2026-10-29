<?php

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Data\Platform\ActivityBucketData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Queries\Platform\PlatformActivityQuery;
use Illuminate\Support\Carbon;
use Tests\Support\Analytics\PlatformScopes;

/**
 * The admin dashboard's activity chart: one bucket per hour, day or month of
 * the window (by its length), every bucket present, with the traffic and the
 * sign-ups that fell in it. Runs in a future month no seeded row falls in.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
});

it('charts the last 7 days day by day, with every day present', function (): void {
    $acme = User::factory()->workspace()->create();
    $acme->workspace()->forceFill(['created_at' => '2031-03-16 09:00:00'])->saveQuietly();
    $bot = createAgent($acme);
    $bot->forceFill(['created_at' => '2031-03-18 11:00:00'])->saveQuietly();

    analyticsRollup($bot, '2031-03-16 09:00:00', ['user_messages' => 2, 'assistant_messages' => 3, 'conversations_started' => 1]);
    analyticsRollup($bot, '2031-03-16 17:00:00', ['user_messages' => 1, 'assistant_messages' => 1]);
    analyticsRollup($bot, '2031-03-20 08:00:00', ['user_messages' => 4, 'assistant_messages' => 4, 'conversations_started' => 2]);

    $series = app(PlatformActivityQuery::class)->get(PlatformScopes::of(AnalyticsPeriod::Last7Days));
    $byDay = collect($series)->keyBy(fn (ActivityBucketData $bucket): string => substr($bucket->start, 0, 10));

    expect($series)->toHaveCount(7)
        ->and($series[0]->label)->toBe('Mar 14')
        ->and($series[6]->label)->toBe('Mar 20')
        ->and($byDay['2031-03-16']->visitorMessages)->toBe(3)
        ->and($byDay['2031-03-16']->aiReplies)->toBe(4)
        ->and($byDay['2031-03-16']->conversations)->toBe(1)
        ->and($byDay['2031-03-16']->newWorkspaces)->toBe(1)
        ->and($byDay['2031-03-18']->newAgents)->toBe(1)
        ->and($byDay['2031-03-20']->messages())->toBe(8)
        ->and($byDay['2031-03-17']->messages())->toBe(0);
});
