<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Media;

use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Models\GeneratedMedia;
use Spatie\LaravelData\Data;

/**
 * Where one generated image or audio file stands, as the chat shows it: the
 * fields an image or audio artifact takes from its row on every load and on
 * every live update. The link is there only once the file is made.
 */
class GeneratedMediaData extends Data
{
    public function __construct(
        public string $id,
        public GeneratedMediaKind $kind,
        public GeneratedMediaStatus $status,
        public ?string $url,
        public ?string $mime_type,
        public ?GeneratedMediaFailure $failure,
    ) {}

    public static function fromModel(GeneratedMedia $media): self
    {
        $ready = $media->status === GeneratedMediaStatus::Ready;

        return new self(
            id: $media->external_id,
            kind: $media->kind,
            status: $media->status,
            url: $ready ? $media->url : null,
            mime_type: $ready ? $media->mime_type : null,
            failure: $media->failure,
        );
    }
}
