<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Media\GeneratedMediaFiles;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Speech\MessageSpeechFiles;

/**
 * Removes a message and the one before it (the question and its answer) from
 * the visitor's live history, and deletes the files uploaded with them, the
 * answer read aloud and the images and audio the answer made, so they do not
 * linger after the visitor erased the turn. Regenerating a reply comes
 * through here too. The chat-log rows stay for the workspace.
 */
class DeleteMessagePairAction
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly MessageSpeechFiles $speechFiles,
        private readonly GeneratedMediaFiles $generatedMedia,
    ) {}

    public function execute(ChatScopeData $scope, string $messageId): void
    {
        $memory = $this->memory->withScope($scope);
        // The cached form, not the display copy: the messages that stay keep
        // the text meant for the model (message_for_llm).
        $messages = $memory->cachedMessages();
        $removedIds = [];
        $removedMetadata = [];

        foreach ($messages as $index => $message) {
            if ($message->id !== $messageId) {
                continue;
            }

            $removedIds[] = $message->id;
            $removedMetadata[] = $message->metadata;

            if (isset($messages[$index - 1])) {
                $removedIds[] = $messages[$index - 1]->id;
                $removedMetadata[] = $messages[$index - 1]->metadata;
            }

            unset($messages[$index], $messages[$index - 1]);
        }

        $this->deleteUploadedFiles($removedIds);

        if ($scope->conversationId !== null) {
            foreach ($removedIds as $removedId) {
                $this->speechFiles->forgetMessage($scope->agent, $scope->conversationId, $removedId);
            }

            $this->forgetGeneratedMedia($scope, $removedMetadata);
        }

        $memory->saveMessages(array_values($messages));
    }

    /**
     * The live history keeps each reply's artifacts, so the media are found
     * there: the chat-log row may not be written yet, or ever.
     *
     * @param  list<array<string, mixed>|null>  $metadataList
     */
    private function forgetGeneratedMedia(ChatScopeData $scope, array $metadataList): void
    {
        $conversationId = Conversation::query()
            ->where('agent_id', $scope->agent->id)
            ->where('external_id', $scope->conversationId)
            ->value('id');

        if ($conversationId !== null) {
            $this->generatedMedia->forgetReplies((int) $conversationId, $metadataList);
        }
    }

    /**
     * Deleting through the model removes each stored object too.
     *
     * @param  array<int, string>  $messageExternalIds
     */
    private function deleteUploadedFiles(array $messageExternalIds): void
    {
        if ($messageExternalIds === []) {
            return;
        }

        $messageIds = Message::query()->whereIn('external_id', $messageExternalIds)->pluck('id');

        if ($messageIds->isEmpty()) {
            return;
        }

        Attachment::query()
            ->whereIn('chat_message_id', $messageIds)
            ->get()
            ->each(static fn (Attachment $attachment) => $attachment->delete());
    }
}
