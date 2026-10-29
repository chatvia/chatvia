<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Widget\WidgetHeaders;
use Illuminate\Testing\TestResponse;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * DELETE /widget/conversations follows the install's chat deletion mode: `end`
 * (the default) refuses, `delete` erases the conversation. It clears nothing of another
 * agent or another visitor, and a conversation id that is not a UUID is unknown, never "clear
 * them all".
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
});

/** Removes the booted conversation, or every one of the session without an id. */
function cdcRemove(Agent $agent, array $boot, bool $all = false): TestResponse
{
    $headers = [WidgetHeaders::SESSION_ID => $boot['session_id']];

    if (! $all) {
        $headers[WidgetHeaders::CONVERSATION_ID] = $boot['conversation_id'];
    }

    return test()->withHeaders(EmbeddedWidget::headers($agent, $headers))->deleteJson('/widget/conversations');
}

it('refuses to remove anything while visitors may only end chats, and erases once they may delete them', function (bool $all): void {
    $boot = EmbeddedWidget::boot($this->agent);

    cdcRemove($this->agent, $boot, $all)
        ->assertForbidden()
        ->assertJson(['error' => 'Removing chats is turned off']);

    $conversation = Conversation::query()->where('external_id', $boot['conversation_id'])->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->cleared_at)->toBeNull();

    config(['chatvia.widget.chat_deletion_mode' => 'delete']);

    cdcRemove($this->agent, $boot, $all)->assertOk();

    $erased = Conversation::withTrashed()->where('external_id', $boot['conversation_id'])->first();
    expect(Conversation::query()->where('external_id', $boot['conversation_id'])->exists())->toBeFalse()
        ->and($erased->trashed())->toBeTrue()
        ->and($erased->deleted_by)->toBe(Conversation::DELETED_BY_VISITOR);
})->with(['one chat' => false, 'all chats' => true]);

it('clears nothing for a conversation of another agent or an id that is not a UUID', function (Closure $conversationId): void {
    config(['chatvia.widget.chat_deletion_mode' => 'hide']);
    $boot = EmbeddedWidget::boot($this->agent);
    $other = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($other);
    $foreign = EmbeddedWidget::boot($other);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $conversationId($foreign),
    ]))
        ->deleteJson('/widget/conversations')
        ->assertNotFound()
        ->assertJson(['error' => 'Invalid thread']);

    expect(Conversation::query()->whereNotNull('cleared_at')->exists())->toBeFalse();
})->with([
    'another agent’s conversation' => [fn (array $foreign): string => $foreign['conversation_id']],
    'an id that is not a UUID' => [fn (): string => 'nope'],
]);

it('never removes or forgets a conversation of another visitor of the same agent', function (string $mode): void {
    config(['chatvia.widget.chat_deletion_mode' => $mode]);
    $mine = EmbeddedWidget::boot($this->agent);
    $theirs = EmbeddedWidget::boot($this->agent);
    $theirMemory = new ChatCache($theirs['session_id'], $this->agent);
    $theirMemory->put('context', ['kept'], $theirs['conversation_id']);

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [
        WidgetHeaders::SESSION_ID => $mine['session_id'],
        WidgetHeaders::CONVERSATION_ID => $theirs['conversation_id'],
    ]))
        ->deleteJson('/widget/conversations')
        ->assertNotFound()
        ->assertJson(['error' => 'Invalid thread']);

    $conversation = Conversation::query()->where('external_id', $theirs['conversation_id'])->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->cleared_at)->toBeNull()
        ->and($theirMemory->get('context', null, $theirs['conversation_id']))->toBe(['kept']);
})->with(['hide', 'delete']);
