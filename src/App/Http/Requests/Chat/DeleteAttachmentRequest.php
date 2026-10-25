<?php

namespace App\Http\Requests\Chat;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatViewer;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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
    public function authorize(#[HostedChatAgent] ?Agent $agent): bool
    {
        $attachment = $this->route('attachment');
        if (! $attachment instanceof Attachment || ! $agent instanceof Agent) {
            return false;
        }

        // Another agent's attachment answers like an unknown id, so a
        // guessed id does not tell whether it exists.
        abort_unless($this->belongsToAgent($attachment, $agent), 404);

        // Composer-only — once bound to a sent message, the attachment's
        // lifecycle follows the message/conversation cascade.
        if ($attachment->chat_message_id !== null) {
            return false;
        }

        if ($attachment->chat_conversation_id === null) {
            return true;
        }

        $conversation = $attachment->conversation;
        if ($conversation === null) {
            return false;
        }

        // Mirrors EnsureChatIsAccessible's protected-agent gate: a signed-in
        // chatter or staff account on a private agent must own the
        // conversation that holds this attachment.
        $viewer = HostedChatViewer::data();

        if ($agent->is_public === false && $viewer instanceof ChatViewerData) {
            return ChatCache::isOwnedBy($conversation->external_id, $viewer, $agent);
        }

        return true;
    }

    /**
     * An orphan upload is the agent's when it sits under the agent's upload
     * folder; a bound one when its conversation (deleted or not) is the agent's.
     */
    private function belongsToAgent(Attachment $attachment, Agent $agent): bool
    {
        if ($attachment->chat_conversation_id === null) {
            return Str::startsWith($attachment->filepath, sprintf('workspaces/%s/agents/%s/attachments/', $agent->workspaceExternalId(), $agent->external_id));
        }

        return $attachment->conversation()->withTrashed()->first()?->session?->agent_id === $agent->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
