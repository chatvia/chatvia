<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Handoff\TakeoverConversationAction;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * Only one teammate can hold a waiting conversation: of two teammates who
 * loaded it while it was waiting, the slower one is refused. The plain
 * takeover and the "someone else holds it" conflict are covered through
 * TakeoverHandoffControllerTest.
 */
it('refuses the second of two teammates who loaded the same waiting conversation', function (): void {
    Event::fake([HandedOff::class]);
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Support');
    $first = teammateWithPermissions($owner, []);
    $second = teammateWithPermissions($owner, []);
    $conversation = Conversation::factory()->forAgent($agent)->handedOff(HandoffStatus::Pending)->create();
    $seenByFirst = Conversation::query()->findOrFail($conversation->id);
    $seenBySecond = Conversation::query()->findOrFail($conversation->id);

    $firstWon = app(TakeoverConversationAction::class)->execute($seenByFirst, $first);
    $secondWon = app(TakeoverConversationAction::class)->execute($seenBySecond, $second);

    expect($firstWon)->toBeTrue()
        ->and($secondWon)->toBeFalse()
        ->and($conversation->fresh()->handoff_user_id)->toBe($first->id);
    Event::assertDispatchedTimes(HandedOff::class, 1);
});
