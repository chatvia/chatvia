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
        /** What the chat shows with the file: the prompt, the start of the text, or the attached file's name. */
        public string $caption = '',
        /** The name of the model the chatter picked; null when the agent offers no choice. */
        public ?string $modelName = null,
    ) {}

    public static function accepted(string $mediaId, string $caption, ?string $modelName = null): self
    {
        return new self(mediaId: $mediaId, caption: $caption, modelName: $modelName);
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
     * status (pending until the job is done), its caption and the picked
     * model's name.
     *
     * @return array{id: string, status: string, prompt: string, model_name: string|null}
     */
    public function artifact(): array
    {
        return ['id' => (string) $this->mediaId, 'status' => GeneratedMediaStatus::Pending->value, 'prompt' => $this->caption, 'model_name' => $this->modelName];
    }
}
