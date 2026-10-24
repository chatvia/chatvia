<?php

use Domain\Conversation\Actions\Removal\DeleteSessionConversationsAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\DB;

/** Erasing every conversation of a visitor's session erases their text at once. */
it('erases the text of every conversation of the session', function (): void {
    $conversation = Conversation::factory()->hostedChat()->visible()->create();
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id, 'content' => 'My card number is 4242']);

    app(DeleteSessionConversationsAction::class)->execute(new ChatScopeData($conversation->agent, $conversation->session->external_id), 'user');

    expect(DB::table('chat_messages')->where('id', $message->id)->value('content'))->toBeNull()
        ->and(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue();
});
