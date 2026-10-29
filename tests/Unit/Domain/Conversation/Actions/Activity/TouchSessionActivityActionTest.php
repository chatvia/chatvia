<?php

use Domain\Conversation\Actions\Activity\TouchSessionActivityAction;
use Domain\Conversation\Models\Session;

/**
 * The session's activity stamp on a widget load moves at most once a minute.
 */
it('leaves a stamp younger than a minute unchanged', function (): void {
    $recent = now()->subSeconds(10)->startOfSecond();
    $session = Session::factory()->create(['last_activity_at' => $recent]);

    app(TouchSessionActivityAction::class)->execute($session);

    expect($session->fresh()->last_activity_at->equalTo($recent))->toBeTrue();
});
