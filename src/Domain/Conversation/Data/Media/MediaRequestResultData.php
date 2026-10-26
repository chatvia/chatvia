<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Media;

use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Enums\MediaRequestRefusal;
use Spatie\LaravelData\Data;

/**
 * The answer to an agent's request for a file: the new item (pending, its
 * job queued), or why it was turned down.
 */
class MediaRequestResultData extends Data
{
    public function __construct(
        /** The external id of the item being made; null when refused. */
        public ?string $mediaId = null,
        public ?MediaRequestRefusal $refusal = null,
    ) {}

    public static function accepted(string $mediaId): self
    {
        return new self(mediaId: $mediaId);
    }

    public static function refused(MediaRequestRefusal $refusal): self
    {
        return new self(refusal: $refusal);
    }

    public function isAccepted(): bool
    {
        return $this->mediaId !== null;
    }

    /**
     * The artifact the reply keeps for the chat to draw: the item's id, its
     * status (pending until the job is done) and what was asked for.
     *
     * @return array{id: string, status: string, prompt: string}
     */
    public function artifact(string $prompt): array
    {
        return ['id' => (string) $this->mediaId, 'status' => GeneratedMediaStatus::Pending->value, 'prompt' => $prompt];
    }
}
