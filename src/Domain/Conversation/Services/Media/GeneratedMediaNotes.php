<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Media;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Str;

/**
 * Tells the model, in its history, about each image, audio file and video a
 * reply asked for. The model never gets the file itself: chat models cannot
 * read audio or video, and a picture would cost its tokens on every turn. A
 * short note per file says what was asked for and where it stands, so a
 * follow-up ("make her laugh", "is it ready?") has something to go on.
 *
 * The status comes from the file's row on every turn
 * ({@see GeneratedMediaArtifacts}): the reply was saved while the file was
 * still being made.
 */
final class GeneratedMediaNotes
{
    /** How much of the description or text a note repeats. */
    private const int DESCRIPTION_LIMIT = 300;

    public function __construct(private readonly GeneratedMediaArtifacts $artifacts) {}

    /**
     * The messages, each reply that asked for files with its notes after its
     * LLM-facing text.
     *
     * @param  list<MessageData>  $messages
     * @return list<MessageData>
     */
    public function forLlm(array $messages, Agent $agent, string $conversationId): array
    {
        $asksForMedia = collect($messages)
            ->contains(static fn (MessageData $message): bool => GeneratedMediaFiles::mediaIds($message->metadata) !== []);

        if (! $asksForMedia) {
            return $messages;
        }

        $conversation = Conversation::query()
            ->where('agent_id', $agent->id)
            ->where('external_id', $conversationId)
            ->first();

        if (! $conversation instanceof Conversation) {
            return $messages;
        }

        return collect($this->artifacts->forHistory($messages, $conversation))
            ->map(function (MessageData $message): MessageData {
                $notes = $this->notes($message->metadata);

                return $notes === []
                    ? $message
                    : $message->withMessageForLlm(collect([$message->message_for_llm ?? $message->content, ...$notes])->filter()->join("\n\n"));
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return list<string>
     */
    private function notes(?array $metadata): array
    {
        $artifacts = $metadata['artifacts'] ?? null;

        if (! is_array($artifacts)) {
            return [];
        }

        return collect($artifacts)
            ->map($this->note(...))
            ->filter()
            ->values()
            ->all();
    }

    private function note(mixed $artifact): ?string
    {
        if (! is_array($artifact) || ! is_array($artifact['data'] ?? null) || blank($artifact['data']['id'] ?? null)) {
            return null;
        }

        $kind = GeneratedMediaKind::tryFrom((string) ($artifact['kind'] ?? ''));

        if (! $kind instanceof GeneratedMediaKind) {
            return null;
        }

        $media = $artifact['data'];
        $description = Str::of((string) ($media['prompt'] ?? ''))->squish()->limit(self::DESCRIPTION_LIMIT)->toString();
        $status = GeneratedMediaStatus::tryFrom((string) ($media['status'] ?? '')) ?? GeneratedMediaStatus::Pending;
        $failure = GeneratedMediaFailure::tryFrom((string) ($media['failure'] ?? ''));

        $asked = match ($kind) {
            GeneratedMediaKind::Image => 'a picture',
            GeneratedMediaKind::Audio => 'an audio file',
            GeneratedMediaKind::Video => 'a video',
        };

        if (filled($description)) {
            $asked .= $kind === GeneratedMediaKind::Audio
                ? " reading \"{$description}\" aloud"
                : " from the description \"{$description}\"";
        }

        $stands = match ($status) {
            GeneratedMediaStatus::Ready => 'It is ready, and the chat shows it.',
            GeneratedMediaStatus::Pending => 'It is still being made, and the chat shows it by itself when it is ready.',
            GeneratedMediaStatus::Failed => match ($failure) {
                GeneratedMediaFailure::Refused => 'It was not made: the service refused the request.',
                GeneratedMediaFailure::TimedOut => 'It was not made: the service took too long.',
                GeneratedMediaFailure::Failed, null => 'It was not made: the service failed.',
            },
        };

        $unseen = $kind === GeneratedMediaKind::Audio ? 'You have not heard it.' : 'You have not seen it.';

        return "[You asked for {$asked}. {$stands} {$unseen}]";
    }
}
