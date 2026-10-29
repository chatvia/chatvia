<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\SendVisitorHandoffMessageAction;
use Domain\Conversation\Data\Handoff\SendVisitorHandoffMessageData;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Models\Conversation;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * A chatter's message during a live chat still counts against the chatter's
 * monthly message limit, like any other message they send.
 */
it('counts a chatter’s relayed message against their monthly limit', function (): void {
    Event::fake([MessagePosted::class]);
    Bus::fake();
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $conversation = Conversation::factory()->forAgent($agent)->ofChatter($chatter)->handedOff(HandoffStatus::Human)->create();
    app(SetMessageLimitAction::class)->execute($chatter, 5);

    app(SendVisitorHandoffMessageAction::class)->execute(
        new SendVisitorHandoffMessageData($conversation, 'Still there?'),
    );

    expect(MessageLimit::query()->where('chatter_id', $chatter->id)->value('messages_used'))->toBe(1);
});
