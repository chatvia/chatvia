<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Removes a message and the one before it (the question and its answer) from
 * the visitor's live history, and deletes the files uploaded with them so
 * they do not linger after the visitor erased the turn. The chat-log rows
 * stay for the workspace.
 */
class DeleteMessagePairAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(ChatScopeData $scope, string $messageId): void
    {
        $memory = $this->memory->withScope($scope);
        $messages = $memory->getMessagesForConversation();
        $removedIds = [];

        foreach ($messages as $index => $message) {
            if ($message['id'] !== $messageId) {
                continue;
            }

            $removedIds[] = $message['id'];

            if (isset($messages[$index - 1]['id'])) {
                $removedIds[] = $messages[$index - 1]['id'];
            }

            unset($messages[$index], $messages[$index - 1]);
        }

        $this->deleteUploadedFiles($removedIds);

        $memory->saveMessages(array_values($messages));
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
