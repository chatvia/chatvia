<?php

declare(strict_types=1);

namespace Tests\Support\Conversation;

use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Bus;

/**
 * The visitor of the test's browser session on an agent's hosted chat. It
 * starts a conversation the way the chat page does, with its first message,
 * and reads that conversation's live history.
 */
final class HostedChatVisitor
{
    /**
     * Start a conversation with its first message and return its id. The
     * queue is faked and the question rewriter is off, so no model is asked.
     */
    public static function startConversation(Agent $agent, string $message = 'Hi'): string
    {
        config(['chatvia.ai.query.rewriter.enabled' => false]);
        Bus::fake();

        return (string) test()->postJson("/chat/{$agent->slug}/conversations/create", ['message' => $message])
            ->assertOk()
            ->json('id');
    }

    /** The live history of the visitor's conversation, as the chat reads it. */
    public static function memory(Agent $agent, string $conversationId): ChatMemory
    {
        return app(ChatMemory::class)->withScope(app(HostedChatSession::class)->scope($agent, $conversationId));
    }
}
