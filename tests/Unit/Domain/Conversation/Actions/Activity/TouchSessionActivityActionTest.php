<?php

use Domain\Conversation\Actions\Activity\TouchSessionActivityAction;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\DB;

/**
 * The session's activity stamp on a widget load: moves last_activity_at
 * forward without touching updated_at, and at most once a minute.
 */
it('stamps the session without touching updated_at', function (): void {
    $session = Session::factory()->create(['last_activity_at' => null]);
    $updatedAt = now()->subHour()->startOfSecond();
    DB::table('chat_sessions')->where('id', $session->id)->update(['updated_at' => $updatedAt]);

    app(TouchSessionActivityAction::class)->execute($session);

    $fresh = $session->fresh();
    expect($fresh->last_activity_at)->not->toBeNull()
        ->and($fresh->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('moves a stamp older than a minute forward', function (): void {
    $session = Session::factory()->create(['last_activity_at' => now()->subMinutes(5)]);

    app(TouchSessionActivityAction::class)->execute($session);

    expect($session->fresh()->last_activity_at->greaterThan(now()->subMinute()))->toBeTrue();
});

it('moves the stamp at most once a minute', function (): void {
    $recent = now()->subSeconds(10)->startOfSecond();
    $session = Session::factory()->create(['last_activity_at' => $recent]);

    app(TouchSessionActivityAction::class)->execute($session);

    expect($session->fresh()->last_activity_at->equalTo($recent))->toBeTrue();
});
