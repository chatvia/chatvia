<?php

namespace App\Http\Requests\Chat;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Authorizes `chat.messages.regenerate`. EnsureChatIsAccessible only stamps
 * per-conversation ownership on routes carrying `{conversationId}`; this
 * route takes the conversation id in the request body, so re-apply the same
 * gate here (mirroring DeleteAttachmentRequest): the conversation must
 * belong to the current agent, and on a protected agent the authenticated
 * chatter must own it — otherwise a chatter could erase turns from another
 * visitor's thread by guessing its UUID.
 */
class RegenerateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agent = current_agent();
        if ($agent === null) {
            return false;
        }

        $conversationId = $this->input('conversation_id');
        if (! is_string($conversationId) || $conversationId === '') {
            return false;
        }

        $conversation = Conversation::query()
            ->where('external_id', $conversationId)
            ->first();

        if ($conversation === null || $conversation->session?->agent_id !== $agent->id) {
            return false;
        }

        // Mirrors EnsureChatIsAccessible's protected-agent gate: an
        // authenticated chatter on a private agent must own the
        // conversation whose turn is being regenerated.
        if ($agent->is_public === false && Auth::guard('web')->check()) {
            return ChatCache::isOwnedBy($conversationId, Auth::id());
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
            'assistant_message_id' => ['required', 'string'],
            'user_message_id' => ['required', 'string'],
        ];
    }
}
