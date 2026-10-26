<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Messages;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Data\Messages\MessageSpeechData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Message;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Speech\MessageSpeechFiles;
use Domain\Conversation\Services\Speech\ReadAloudAvailability;
use Domain\Conversation\Services\Speech\SpokenText;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Exceptions\SpeechFailedException;
use Domain\Llm\Exceptions\SpeechNotConfiguredException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Reads an agent reply aloud for the visitor it was written for: the saved
 * file when the reply was read with this service and voice before, else the
 * speech service reads it (in parts when it is long, as one file) and the
 * file is saved ({@see MessageSpeechFiles}). Two presses at
 * once pay for one reading. The reply is looked up in the live memory first,
 * so a chat whose log is off works too, then in the chat log; only replies
 * in the conversation in scope can be read.
 */
class SpeakMessageAction
{
    /** Long enough for a long reply read in several parts. */
    private const int LOCK_SECONDS = 300;

    public function __construct(
        private readonly ChatMemory $memory,
        private readonly Speaker $speaker,
        private readonly ReadAloudAvailability $availability,
        private readonly SpokenText $spoken,
        private readonly MessageSpeechFiles $files,
    ) {}

    /**
     * @return MessageSpeechData|null null when the conversation in scope has no such reply, or nothing in it to say
     *
     * @throws SpeechNotConfiguredException when no text-to-speech service is set up
     * @throws SpeechFailedException when the service could not read the reply
     */
    public function execute(ChatScopeData $scope, string $messageId): ?MessageSpeechData
    {
        $conversationId = $scope->conversationId
            ?? throw new InvalidArgumentException('A conversation id is required to read a message aloud.');

        if (! $this->speaker->isEnabled()) {
            throw new SpeechNotConfiguredException;
        }

        $text = $this->spoken->from($this->replyText($scope, $conversationId, $messageId) ?? '');

        // No such reply, or nothing in it to say (only code, say).
        if ($text === '') {
            return null;
        }

        $voice = $this->availability->voiceFor($scope->agent);
        $path = $this->files->pathFor($scope->agent, $conversationId, $messageId, $this->speaker->name(), $voice, $this->speaker->format());
        $disk = Storage::disk(MessageSpeechFiles::DISK);

        if (! $disk->exists($path)) {
            Cache::lock('speech:'.$path, self::LOCK_SECONDS)->block(self::LOCK_SECONDS, function () use ($disk, $path, $text, $voice): void {
                if ($disk->exists($path)) {
                    return;
                }

                $disk->put($path, $this->speaker->speak($text, $voice));
            });
        }

        return new MessageSpeechData(MessageSpeechFiles::DISK, $path, $this->speaker->format()->mimeType());
    }

    /** The reply's text, from the live memory or else the chat log; null when the visitor has no such reply. */
    private function replyText(ChatScopeData $scope, string $conversationId, string $messageId): ?string
    {
        $live = $this->memory->withScope($scope)->getMessageById($messageId);

        if ($live instanceof MessageData) {
            return $live->role === MessageRole::Assistant && filled($live->content) ? $live->content : null;
        }

        $stored = Message::query()
            ->where('external_id', $messageId)
            ->where('role', MessageRole::Assistant)
            ->whereHas('conversation', static function (ConversationQueryBuilder $query) use ($scope, $conversationId): void {
                $query->inSession($scope->sessionId, $scope->agent->id)->where('external_id', $conversationId);
            })
            ->value('content');

        return filled($stored) ? (string) $stored : null;
    }
}
