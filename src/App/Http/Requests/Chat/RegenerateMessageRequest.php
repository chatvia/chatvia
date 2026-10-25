<?php

namespace App\Http\Requests\Chat;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatViewer;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes `chat.messages.regenerate`. EnsureChatIsAccessible only stamps
 * per-conversation ownership on routes carrying `{conversationId}`; this
 * route takes the conversation id in the request body, so re-apply the same
 * gate here (mirroring DeleteAttachmentRequest): the conversation must
 * belong to the current agent, and on a protected agent the authenticated
 * chatter must own it — otherwise a chatter could erase turns from another
 * visitor's thread by guessing its UUID.
 *
 * A conversation that's missing/deleted or owned by another agent is a 404
 * ("gone"), while a real ownership-gate failure is a 403 — the two are kept
 * distinct so a stale tab acting on a deleted thread reports the honest
 * status instead of a misleading "forbidden".
 */
class RegenerateMessageRequest extends FormRequest
{
    public function authorize(#[HostedChatAgent] ?Agent $agent): bool
    {
        if (! $agent instanceof Agent) {
            return false;
        }

        $conversationId = $this->input('conversation_id');
        if (blank($conversationId) || is_array($conversationId)) {
            return false;
        }

        $conversation = Conversation::query()
            ->forAgent($agent->id)
            ->where('external_id', $conversationId)
            ->first();

        // A missing/deleted conversation (the SoftDeletes scope hides a
        // soft-deleted row), or one belonging to another agent, is a "gone"
        // resource — not an authorization failure. Surface it as 404 so a
        // stale tab regenerating a thread that was deleted elsewhere doesn't
        // read as a forbidden (or CSRF) error. The genuine ownership gate
        // below still returns 403.
        if ($conversation === null || $conversation->session?->agent_id !== $agent->id) {
            abort(404, __('Conversation not found.'));
        }

        // Mirrors EnsureChatIsAccessible's protected-agent gate: a signed-in
        // chatter or staff account on a private agent must own the
        // conversation whose turn is being regenerated.
        $viewer = HostedChatViewer::data();

        if ($agent->is_public === false && $viewer instanceof ChatViewerData) {
            return ChatCache::isOwnedBy($conversationId, $viewer, $agent);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'string'],
            // Missing when the answer never landed (the stream was cut off).
            'assistant_message_id' => ['nullable', 'string'],
            'user_message_id' => ['required', 'string'],
        ];
    }

    public function conversationId(): string
    {
        return $this->string('conversation_id')->toString();
    }

    public function assistantMessageId(): ?string
    {
        return $this->filled('assistant_message_id') ? $this->string('assistant_message_id')->toString() : null;
    }

    public function userMessageId(): string
    {
        return $this->string('user_message_id')->toString();
    }
}
