<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Media;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\ChatStoragePath;

/**
 * Where an agent's generated images, audio and videos are kept, and how they go: one
 * file per row on the private disk in the conversation's chat folder, by kind
 * (`…/agents/{agent}/chat/{conversation}/images/`, see {@see ChatStoragePath}),
 * so deleting the conversation, purging the agent or deleting the workspace
 * wipes them with the rest.
 *
 * Every path that deletes a conversation or a reply calls this, because the
 * bulk deletes and the foreign-key cascade skip model events: deleting the
 * rows through the model here removes each stored file.
 */
final class GeneratedMediaFiles
{
    /** The file of one generated item. */
    public function pathFor(Agent $agent, string $conversationId, GeneratedMedia $media, string $extension): string
    {
        return ChatStoragePath::forConversation($agent, $conversationId, ChatStoragePath::kindFolder($media->kind->value)).'/'.$media->external_id.'.'.$extension;
    }

    /**
     * Every generated item of these conversations.
     *
     * @param  iterable<int, int>  $conversationIds
     */
    public function forgetConversations(iterable $conversationIds): void
    {
        $ids = collect($conversationIds)->map(static fn (int|string $id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        // lazyById, not each(): offset chunking would skip rows while the
        // same rows are being deleted.
        GeneratedMedia::query()
            ->whereIn('chat_conversation_id', $ids)
            ->lazyById()
            ->each(static fn (GeneratedMedia $media) => $media->delete());
    }

    /**
     * The items these replies made (from their artifacts), when a reply is
     * erased without its conversation. Only rows of that conversation go.
     *
     * @param  iterable<int, array<string, mixed>|null>  $metadataList  each reply's `metadata`
     */
    public function forgetReplies(int $conversationId, iterable $metadataList): void
    {
        $mediaIds = collect($metadataList)
            ->flatMap(static fn (?array $metadata): array => self::mediaIds($metadata))
            ->unique()
            ->values();

        if ($mediaIds->isEmpty()) {
            return;
        }

        GeneratedMedia::query()
            ->where('chat_conversation_id', $conversationId)
            ->whereIn('external_id', $mediaIds)
            ->get()
            ->each(static fn (GeneratedMedia $media) => $media->delete());
    }

    /**
     * The generated items a reply points at: the ids of its image, audio and video
     * artifacts.
     *
     * @param  array<string, mixed>|null  $metadata
     * @return list<string>
     */
    public static function mediaIds(?array $metadata): array
    {
        $artifacts = $metadata['artifacts'] ?? [];

        if (! is_array($artifacts)) {
            return [];
        }

        $kinds = array_map(static fn (GeneratedMediaKind $kind): string => $kind->value, GeneratedMediaKind::cases());

        return collect($artifacts)
            ->filter(static fn (mixed $artifact): bool => is_array($artifact)
                && in_array($artifact['kind'] ?? null, $kinds, true)
                && filled($artifact['data']['id'] ?? null))
            ->map(static fn (array $artifact): string => (string) $artifact['data']['id'])
            ->values()
            ->all();
    }
}
