<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\AddMessageToConversationAction;
use Domain\Conversation\Data\Conversations\CreateConversationData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatStoragePath;
use Domain\Guardrail\Services\GuardrailInspector;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Opens a hosted-chat conversation from the visitor's first message: starts
 * the conversation, records the message (PII masked), and claims the files
 * uploaded before the conversation existed, moving them from the agent's
 * pending folder into the conversation's ({@see ChatStoragePath}). The answer streams afterwards
 * through the normal turn, which reuses this recorded message.
 */
class CreateConversationAction
{
    public function __construct(
        private readonly StartConversationAction $startConversation,
        private readonly AddMessageToConversationAction $addMessage,
        private readonly GuardrailInspector $guardrails,
    ) {}

    /**
     * @return string the new conversation's external id
     */
    public function execute(CreateConversationData $data): string
    {
        $agent = $data->scope->agent;

        $conversationId = $this->startConversation->execute($data->scope, ConversationSource::HostedChat)->external_id;
        $scope = $data->scope->forConversation($conversationId);

        // PII is masked before anything is stored, so the record and the
        // replayed history never hold a raw identifier. The streaming turn
        // masks the same text the same way, so its match on this first
        // message still lines up. Blocking, and any rewrite of the question
        // for search, are left to the streaming turn.
        $masked = $this->guardrails->redactPii($agent, $data->message);

        $this->addMessage->execute($scope, new UserTurnData(
            external_session_id: $data->scope->sessionId,
            external_conversation_id: $conversationId,
            external_message_id: Str::uuid7()->toString(),
            llm_id: $agent->llm_id,
            message: $masked,
            sent_at: now(),
            message_for_llm: $masked,
            chatter_id: $data->chatterId,
            ip_address: $data->ipAddress,
            user_agent: $data->userAgent,
            referrer_url: $data->referrerUrl,
            page_url: $data->pageUrl,
            locale: $data->locale,
        ));

        // Files uploaded on the home screen, before the conversation existed,
        // now belong to it; PDFs among them route the next turn to the
        // document LLM.
        $this->claimAttachments($scope->sessionId, $conversationId, $data->attachmentIds, $agent);

        return $conversationId;
    }

    /**
     * @param  array<int, string>  $attachmentIds  external ids of orphan rows
     */
    private function claimAttachments(string $sessionId, string $conversationId, array $attachmentIds, Agent $agent): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $conversation = Conversation::query()
            ->inSession($sessionId, $agent->id)
            ->where('external_id', $conversationId)
            ->first();

        if ($conversation === null) {
            return;
        }

        // The same per-chat ceiling as the upload limit: a script that skipped
        // the composer and uploaded more orphans cannot smuggle them all in.
        $max = (int) config('chatvia.agent.files.upload.max_files');
        $pending = ChatStoragePath::pending($agent).'/';
        $folder = ChatStoragePath::forConversation($agent, $conversationId).'/';
        $disk = Storage::disk(Attachment::DISK);

        Attachment::query()
            ->whereIn('external_id', array_slice($attachmentIds, 0, $max))
            ->where('workspace_id', $conversation->workspace_id)
            ->whereNull('chat_conversation_id')
            // Only this agent's orphans: a guessed external id can never pull
            // another agent's upload into this conversation.
            ->whereLike('filepath', "{$pending}%", caseSensitive: true)
            ->get()
            ->each(function (Attachment $attachment) use ($conversation, $pending, $folder, $disk): void {
                $path = $folder.Str::after($attachment->filepath, $pending);

                // A failed move keeps the file in the pending folder: the row
                // still deletes it, and so does purging the agent.
                $moved = rescue(fn (): bool => $disk->move($attachment->filepath, $path), false);

                $attachment->update([
                    'chat_conversation_id' => $conversation->id,
                    'filepath' => $moved ? $path : $attachment->filepath,
                ]);
            });
    }
}
