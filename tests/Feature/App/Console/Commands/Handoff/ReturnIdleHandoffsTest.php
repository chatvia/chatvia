<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\Event;

/**
 * Live chats nobody has written in for the agent's idle time go back to the
 * AI; a chat with a recent message from either side stays with the person.
 */
beforeEach(function (): void {
    Event::fake([HandedOff::class]);
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
});

function rihHeldChat(mixed $test, int $minutesAgo): Conversation
{
    $conversation = Conversation::factory()->forAgent($test->agent)->visible()
        ->handedOff(HandoffStatus::Human, $test->owner)
        ->create(['handoff_at' => now()->subMinutes($minutesAgo)]);
    Message::factory()->for($conversation)->fromUser()->create(['created_at' => now()->subMinutes($minutesAgo)]);

    return $conversation;
}

it('gives a quiet live chat back to the AI', function (): void {
    $conversation = rihHeldChat($this, 45);

    $this->artisan('chatvia:handoff:return-idle')->assertSuccessful();

    expect($conversation->fresh())
        ->handoff_status->toBe(HandoffStatus::Ai)
        ->handoff_user_id->toBeNull();
    Event::assertDispatched(HandedOff::class, fn (HandedOff $event): bool => $event->status === HandoffStatus::Returned->value
        && $event->adminId === (string) $this->owner->id);
});

it('keeps a live chat with a recent message with the person', function (): void {
    $conversation = rihHeldChat($this, 45);
    Message::factory()->for($conversation)->fromUser()->create(['created_at' => now()->subMinutes(2)]);

    $this->artisan('chatvia:handoff:return-idle')->assertSuccessful();

    expect($conversation->fresh()->handoff_user_id)->toBe($this->owner->id);
    Event::assertNotDispatched(HandedOff::class);
});

it('waits for the agent\'s own idle time', function (): void {
    $this->agent->setSetting('handoff_idle_minutes', 120, 'widget');
    $conversation = rihHeldChat($this, 45);

    $this->artisan('chatvia:handoff:return-idle')->assertSuccessful();

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Human);
});
