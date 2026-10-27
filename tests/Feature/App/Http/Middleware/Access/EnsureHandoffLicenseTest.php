<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * The `license.handoff` route middleware: without a license key nobody can
 * take a chat over (live chat is a free-use limit), but the person who
 * already holds one can still reply and give it back, so the chat finishes.
 * Any paid key (Pro or Enterprise) opens the takeover again.
 */
beforeEach(function (): void {
    Event::fake([HandedOff::class, MessagePosted::class]);
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
});

function ehlRoute(string $name, Conversation $conversation): string
{
    return route("agents.handoff.{$name}", ['agent' => test()->agent->external_id, 'conversation' => $conversation->external_id]);
}

it('refuses a takeover without a license key', function (): void {
    $conversation = Conversation::factory()->forAgent($this->agent)->visible()->handedOff(HandoffStatus::Pending)->create();

    $this->actingAs($this->owner)
        ->postJson(ehlRoute('takeover', $conversation))
        ->assertForbidden()
        ->assertJsonPath('message', 'This feature is unavailable. Please contact your administrator.');

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending);
    Event::assertNotDispatched(HandedOff::class);
});

it('lets the holder finish a chat without a license key', function (): void {
    $conversation = Conversation::factory()->forAgent($this->agent)->visible()->handedOff(HandoffStatus::Human, $this->owner)->create();

    $this->actingAs($this->owner)
        ->postJson(ehlRoute('message', $conversation), ['content' => 'Still here.'])
        ->assertSuccessful();

    $this->postJson(ehlRoute('return', $conversation))
        ->assertSuccessful()
        ->assertJsonPath('handoff_status', HandoffStatus::Ai->value);

    Event::assertDispatched(MessagePosted::class);
});

it('opens the takeover with a paid license key', function (Closure $license): void {
    $license();
    $conversation = Conversation::factory()->forAgent($this->agent)->visible()->handedOff(HandoffStatus::Pending)->create();

    $this->actingAs($this->owner)
        ->postJson(ehlRoute('takeover', $conversation))
        ->assertSuccessful();

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Human);
})->with([
    'Pro' => [fn () => withLicense()],
    'Enterprise' => [fn () => withEnterpriseLicense()],
]);
