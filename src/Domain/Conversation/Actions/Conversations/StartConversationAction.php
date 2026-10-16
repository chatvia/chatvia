<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ConversationData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\DB;

/**
 * Opens a new, empty conversation in a visitor session, on the surface it
 * came from. The session row is created when missing, the conversation row is
 * created at once (so the widget can subscribe to it and read its handoff
 * state before the first message), the viewer is recorded as its owner, and
 * it joins the session's cached list — hidden until it is named or used.
 * While hidden it expires after {@see Conversation::HIDDEN_LIFETIME_DAYS}.
 */
class StartConversationAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(ChatScopeData $scope, ConversationSource $source, string $title = Conversation::DEFAULT_TITLE): Conversation
    {
        $agent = $scope->agent;
        $memory = $this->memory->withScope($scope);

        // Read the list before the new row exists, so a cold-cache rebuild
        // from the database can't count the row and then append it again.
        $conversations = $memory->conversations();

        $conversation = DB::transaction(function () use ($scope, $source, $title): Conversation {
            $session = Session::firstOrCreate([
                'external_id' => $scope->sessionId,
                'agent_id' => $scope->agent->id,
            ], ['workspace_id' => $scope->agent->workspace_id]);

            return Conversation::create([
                'workspace_id' => $scope->agent->workspace_id,
                'chat_session_id' => $session->id,
                'agent_id' => $scope->agent->id,
                'source' => $source,
                'title' => $title,
                // The column default, set explicitly so the returned model
                // carries it without a refresh.
                'is_visible' => false,
                // Hidden until it is used, so it gets a short life: most are
                // never used, and the daily prune sweeps them. The first
                // message gives it the full window (ChatLogWriter).
                'expires_at' => now()->addDays(Conversation::HIDDEN_LIFETIME_DAYS),
            ]);
        });

        // Lets the hosted chat confirm that the signed-in viewer owns this
        // conversation without a database join (see ChatCache::rememberOwner).
        ChatCache::rememberOwner($conversation->external_id, $scope->viewer, $agent->workspaceExternalId());

        $memory->saveConversations([
            ...$conversations,
            new ConversationData(
                id: $conversation->external_id,
                title: $title,
                timestamp: now()->timestamp,
                is_visible: false,
            )->toArray(),
        ]);

        return $conversation;
    }
}
