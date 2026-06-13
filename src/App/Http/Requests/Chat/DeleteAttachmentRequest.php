<?php

namespace App\Http\Requests\Chat;

use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Authorizes the composer-X delete on `chat.messages.attachments.delete`.
 * EnsureChatIsAccessible only stamps per-conversation ownership on routes
 * carrying `{conversationId}`; this route binds `{attachment}`, so we
 * re-apply the same gate here plus a composer-only guard so an attacker
 * can't lop files off a sent message by guessing its UUID.
 *
 * On the orphan branch the only ambient secret is the attachment's
 * `external_id` (a 128-bit UUID returned exclusively in the uploader's own
 * POST response). The filepath-prefix check is a defense-in-depth
 * segregation by agent, not an identity check — that gap is tracked
 * separately (per-chatter ownership column on chat_attachments).
 */
class DeleteAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attachment = $this->route('attachment');
        if (! $attachment instanceof Attachment) {
            return false;
        }

        // Composer-only — once bound to a sent message, the attachment's
        // lifecycle follows the message/conversation cascade.
        if ($attachment->chat_message_id !== null) {
            return false;
        }

        $agent = current_agent();
        if ($agent === null) {
            return false;
        }

        if ($attachment->chat_conversation_id === null) {
            return str_starts_with(
                $attachment->filepath,
                sprintf('chat-uploads/agent-%d/', $agent->id)
            );
        }

        $conversation = $attachment->conversation;
        if ($conversation === null || $conversation->session?->agent_id !== $agent->id) {
            return false;
        }

        // Mirrors EnsureChatIsAccessible's protected-agent gate: an
        // authenticated chatter on a private agent must own the
        // conversation that holds this attachment.
        if ($agent->is_public === false && Auth::guard('web')->check()) {
            return ChatCache::isOwnedBy($conversation->external_id, Auth::id(), $agent->workspaceExternalId());
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
