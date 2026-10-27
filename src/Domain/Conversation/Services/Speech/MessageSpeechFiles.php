<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Speech;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\ChatStoragePath;
use Domain\Conversation\Services\ConversationFiles;
use Domain\Llm\Enums\SpeechFormat;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where a reply read aloud is saved, so playing it again costs nothing: one
 * audio file per message, service and voice, on the private disk in the
 * conversation's chat folder (`…/agents/{agent}/chat/{conversation}/speech/`,
 * see {@see ChatStoragePath}), so deleting the conversation
 * ({@see ConversationFiles}), purging the agent or deleting the workspace
 * wipes it with the rest. Deleting a message removes its recordings here.
 */
final class MessageSpeechFiles
{
    public const string DISK = 'private';

    /** The file of a message read with this service and voice. */
    public function pathFor(Agent $agent, string $conversationId, string $messageId, string $speaker, string $voice, SpeechFormat $format): string
    {
        $variant = Str::substr(hash('sha256', $speaker."\n".$voice), 0, 16);

        return $this->folder($agent, $conversationId).'/'.$messageId.'-'.$variant.'.'.$format->extension();
    }

    /** Every recording of one message, in any voice. */
    public function forgetMessage(Agent $agent, string $conversationId, string $messageId): void
    {
        $disk = Storage::disk(self::DISK);
        $files = collect($disk->files($this->folder($agent, $conversationId)))
            ->filter(fn (string $path): bool => Str::startsWith(Str::afterLast($path, '/'), $messageId.'-'))
            ->all();

        if ($files !== []) {
            $disk->delete($files);
        }
    }

    private function folder(Agent $agent, string $conversationId): string
    {
        return ChatStoragePath::forConversation($agent, $conversationId, 'speech');
    }
}
