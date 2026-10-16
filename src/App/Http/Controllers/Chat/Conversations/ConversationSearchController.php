<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Conversations\ConversationSearchHitData;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Queries\SearchSessionConversationsQuery;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Search the current chat session's conversations from the sidebar palette
 * ({@see SearchSessionConversationsQuery}).
 */
class ConversationSearchController
{
    /**
     * @return Collection<int, ConversationSearchHitData>
     */
    public function __invoke(
        Request $request,
        #[HostedChatAgent] ?Agent $agent,
        HostedChatSession $chatSession,
        WidgetVerification $verification,
        SearchSessionConversationsQuery $search,
    ): Collection {
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return collect();
        }

        // A search is a read: look up the visitor's session, never create one.
        $sessionId = $agent instanceof Agent ? $chatSession->find($agent) : null;

        if (! $agent instanceof Agent || $sessionId === null) {
            return collect();
        }

        $session = $verification->verifySessionExternalId($sessionId, $agent);

        if (! $session instanceof Session) {
            return collect();
        }

        return $search->forSession($session, $term);
    }
}
