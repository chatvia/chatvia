<?php

use Domain\Analytics\Actions\Retention\PruneAnalyticsEventsAction;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Services\AnalyticsDatabase;
use Illuminate\Support\Str;

function paeEvent(AnalyticsEventType $type, string $occurredAt): void
{
    app(AnalyticsDatabase::class)->table('analytics_events')->insert([
        'agent_id' => 910001,
        'workspace_id' => 910002,
        'event_type' => $type->value,
        'message_external_id' => (string) Str::uuid(),
        'occurred_at' => $occurredAt,
    ]);
}

it('deletes old message events but keeps feedback and recent rows', function (): void {
    paeEvent(AnalyticsEventType::MessageSent, now()->subDays(400)->toDateTimeString());
    paeEvent(AnalyticsEventType::MessageSent, now()->subDays(10)->toDateTimeString());
    paeEvent(AnalyticsEventType::FeedbackGiven, now()->subDays(400)->toDateTimeString());

    $deleted = app(PruneAnalyticsEventsAction::class)->execute(now()->subDays(395));

    $remaining = app(AnalyticsDatabase::class)->table('analytics_events')->where('workspace_id', 910002)->pluck('event_type')->sort()->values()->all();

    expect($deleted)->toBe(1)
        ->and($remaining)->toBe([AnalyticsEventType::FeedbackGiven->value, AnalyticsEventType::MessageSent->value]);
});
