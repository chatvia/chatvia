<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Media;

use Domain\Conversation\Data\Media\GeneratedMediaData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Collection;

/**
 * Brings each image and audio artifact of a reply up to date from its row.
 * A reply stores its artifact while the file is still being made, and the
 * history the chat reads (live memory or the chat log) keeps that copy, so
 * every load overwrites the artifact's status, link and type with the
 * row's. One query per list of messages, scoped to the conversation(s) they
 * belong to, so a reply can never show another conversation's file.
 */
final class GeneratedMediaArtifacts
{
    /**
     * A conversation's history, as the hosted chat loads it.
     *
     * @param  list<MessageData>  $messages
     * @return list<MessageData>
     */
    public function forHistory(array $messages, Conversation $conversation): array
    {
        $rows = $this->rows(
            collect($messages)->flatMap(static fn (MessageData $message): array => GeneratedMediaFiles::mediaIds($message->metadata)),
            [$conversation->id],
        );

        if ($rows->isEmpty()) {
            return $messages;
        }

        return array_map(
            fn (MessageData $message): MessageData => GeneratedMediaFiles::mediaIds($message->metadata) === []
                ? $message
                : $message->withMetadata($this->apply($message->metadata, $rows, $conversation->id)),
            $messages,
        );
    }

    /**
     * Chat-log rows, updated in place (never saved).
     *
     * @param  iterable<int, Message>  $messages
     */
    public function forChatLog(iterable $messages): void
    {
        $messages = collect($messages);
        $rows = $this->rows(
            $messages->flatMap(static fn (Message $message): array => GeneratedMediaFiles::mediaIds($message->metadata)),
            $messages->pluck('chat_conversation_id')->unique()->values()->all(),
        );

        if ($rows->isEmpty()) {
            return;
        }

        $messages->each(function (Message $message) use ($rows): void {
            if (GeneratedMediaFiles::mediaIds($message->metadata) !== []) {
                $message->metadata = $this->apply($message->metadata, $rows, $message->chat_conversation_id);
            }
        });
    }

    /**
     * @param  Collection<int, string>  $mediaIds
     * @param  list<int>  $conversationIds
     * @return Collection<string, GeneratedMedia> keyed by external id
     */
    private function rows(Collection $mediaIds, array $conversationIds): Collection
    {
        if ($mediaIds->isEmpty() || $conversationIds === []) {
            return collect();
        }

        return GeneratedMedia::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->whereIn('external_id', $mediaIds->unique()->values())
            ->get()
            ->keyBy('external_id');
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  Collection<string, GeneratedMedia>  $rows
     * @return array<string, mixed>|null
     */
    private function apply(?array $metadata, Collection $rows, int $conversationId): ?array
    {
        if (! is_array($metadata['artifacts'] ?? null)) {
            return $metadata;
        }

        foreach ($metadata['artifacts'] as &$artifact) {
            $id = is_array($artifact) && is_array($artifact['data'] ?? null) ? ($artifact['data']['id'] ?? null) : null;
            $row = filled($id) ? $rows->get($id) : null;

            if ($row instanceof GeneratedMedia && $row->chat_conversation_id === $conversationId && $row->kind->value === ($artifact['kind'] ?? null)) {
                $artifact['data'] = [...$artifact['data'], ...GeneratedMediaData::fromModel($row)->toArray()];
            }
        }
        unset($artifact);

        return $metadata;
    }
}
